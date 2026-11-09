<?php

namespace ME\MerchandisingSfl\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\PackingFigures;

/**
 * Commercial invoice header (shipping fields) + its carton packing list (cartons[]):
 * each row = cartons carton_from … carton_to of one PO, pcs per size in each carton,
 * weight per carton (kg) and carton size (cm). Invoice qty / cartons / weights / CBM come from them.
 */
class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('invoice') ? 'msfl_com_invoice.edit' : 'msfl_com_invoice.add');
    }

    /** Rows without a PO or any pcs are ignored; sizes keep only qty > 0. */
    protected function prepareForValidation(): void
    {
        $rows = [];
        foreach ((array) $this->input('cartons', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $row['sizes'] = array_filter((array) ($row['sizes'] ?? []), fn ($q) => (int) $q > 0);
            if (filled($row['order_po_id'] ?? null) || $row['sizes']) {
                $rows[] = $row;
            }
        }
        $this->merge(['cartons' => $rows]);
    }

    public function rules(): array
    {
        $num = ['nullable', 'numeric', 'min:0'];

        return [
            'export_lc_id' => ['required', Rule::exists('msfl_com_export_lcs', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'invoice_date' => ['required', 'date'],
            'exp_no' => ['nullable', 'string', 'max:50'],
            'exp_date' => ['nullable', 'date'],
            'ship_mode_id' => ['nullable', Rule::exists('msfl_ship_modes', 'id')],
            'bl_no' => ['nullable', 'string', 'max:100'],
            'bl_date' => ['nullable', 'date'],
            'vessel' => ['nullable', 'string', 'max:150'],
            'container_no' => ['nullable', 'string', 'max:150'],
            'port_of_loading' => ['nullable', 'string', 'max:100'],
            'port_of_discharge' => ['nullable', 'string', 'max:100'],
            'final_destination' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'cartons' => ['required', 'array', 'min:1'],
            'cartons.*.order_po_id' => ['required', 'integer'],
            'cartons.*.carton_from' => ['required', 'integer', 'min:1'],
            'cartons.*.carton_to' => ['required', 'integer', 'min:1'],
            'cartons.*.sizes' => ['required', 'array', 'min:1'],
            'cartons.*.sizes.*' => ['integer', 'min:0'],
            'cartons.*.net_weight' => $num,
            'cartons.*.gross_weight' => $num,
            'cartons.*.length' => $num,
            'cartons.*.width' => $num,
            'cartons.*.height' => $num,
        ];
    }

    public function attributes(): array
    {
        $out = [];
        foreach (array_keys((array) $this->input('cartons', [])) as $i) {
            foreach (['order_po_id' => 'PO', 'carton_from' => 'Carton From', 'carton_to' => 'Carton To', 'sizes' => 'Pcs per size'] as $f => $l) {
                $out["cartons.{$i}.{$f}"] = 'Row ' . ($i + 1) . " — {$l}";
            }
        }

        return $out;
    }

    public function messages(): array
    {
        return ['cartons.required' => 'Add at least one carton row (PO, carton numbers and pcs per size).', 'export_lc_id.exists' => 'Pick an active Export LC.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $lc = ExportLc::findOrFail($this->export_lc_id);
            $lcPoIds = $lc->pos()->pluck('msfl_order_pos.id')->all();
            $invoice = $this->route('invoice');
            $rows = $this->input('cartons');

            // Ranges: to ≥ from, and no carton number used twice.
            foreach ($rows as $i => $r) {
                if ((int) $r['carton_to'] < (int) $r['carton_from']) {
                    $validator->errors()->add("cartons.{$i}.carton_to", 'Row ' . ($i + 1) . ': "Carton To" is before "Carton From".');
                }
                foreach ($rows as $j => $o) {
                    if ($j > $i && (int) $r['carton_from'] <= (int) $o['carton_to'] && (int) $o['carton_from'] <= (int) $r['carton_to']) {
                        $validator->errors()->add("cartons.{$j}.carton_from", 'Row ' . ($j + 1) . ': carton numbers overlap row ' . ($i + 1) . '.');
                    }
                }
            }

            // Per PO and size: pcs on this invoice ≤ packed − already on other invoices.
            $want = [];
            foreach ($rows as $i => $r) {
                if (! in_array((int) $r['order_po_id'], $lcPoIds, true)) {
                    $validator->errors()->add("cartons.{$i}.order_po_id", 'Row ' . ($i + 1) . ': the PO is not on this LC.');
                    continue;
                }
                $count = max(0, (int) $r['carton_to'] - (int) $r['carton_from'] + 1);
                foreach ($r['sizes'] as $sizeId => $qty) {
                    $want[(int) $r['order_po_id']][(int) $sizeId] = ($want[(int) $r['order_po_id']][(int) $sizeId] ?? 0) + (int) $qty * $count;
                }
            }
            $figures = app(PackingFigures::class);
            foreach (OrderPo::with(['style', 'sizes.size'])->whereIn('id', array_keys($want))->get() as $po) {
                $f = $figures->forPo($po, $invoice?->id);
                $names = $f['sizes']->pluck('name', 'id');
                foreach ($want[$po->id] as $sizeId => $qty) {
                    if (! $names->has($sizeId)) {
                        $validator->errors()->add('cartons', "PO {$po->po_no} · " . ($po->style->style_no ?? '') . ': a size that is not in the PO has pcs.');
                        continue;
                    }
                    $free = max(0, ($f['packed'][$sizeId] ?? 0) - ($f['invoiced'][$sizeId] ?? 0));
                    if ($qty > $free) {
                        $validator->errors()->add('cartons', "PO {$po->po_no} · " . ($po->style->style_no ?? '') . " size {$names[$sizeId]}: {$qty} pcs in cartons, but only {$free} can be shipped (packed " . ($f['packed'][$sizeId] ?? 0) . ', already invoiced ' . ($f['invoiced'][$sizeId] ?? 0) . ').');
                    }
                }
            }
        }];
    }
}
