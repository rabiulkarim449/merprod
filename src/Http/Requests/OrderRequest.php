<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Services\OrderPoLines;

/**
 * Order header + its PO lines (pos[]), all from the one-page order form. size_ids = the
 * sizes this order uses (size columns); a line's color is its style's color (Master Data → Styles).
 */
class OrderRequest extends FormRequest
{
    private const FLAGS = ['needs_embroidery', 'needs_washing', 'applique_ih', 'studs_stones_ih', 'heat_seal_ih'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('order') ? 'msfl_order.edit' : 'msfl_order.add');
    }

    /** Rows left completely empty (no PO no, style, color or qty) are ignored. */
    protected function prepareForValidation(): void
    {
        $rows = array_values(array_filter((array) $this->input('pos', []), fn ($row) => is_array($row) && (
            ! empty($row['id']) || filled($row['po_no'] ?? null) || filled($row['style_id'] ?? null) || filled($row['color_id'] ?? null)
            || array_sum(array_map('intval', (array) ($row['sizes'] ?? []))) > 0
        )));

        // One color per style: a style with a color gives it to its lines; only the picked sizes count.
        $styleColors = Style::query()->whereIn('id', array_column($rows, 'style_id'))->whereNotNull('color_id')->pluck('color_id', 'id');
        $sizeIds = array_map('intval', (array) $this->input('size_ids', []));
        foreach ($rows as &$row) {
            if (isset($styleColors[$row['style_id'] ?? null])) {
                $row['color_id'] = $styleColors[$row['style_id']];
            }
            $row['sizes'] = array_intersect_key((array) ($row['sizes'] ?? []), array_flip($sizeIds));
        }
        unset($row);

        $this->merge(['pos' => $rows]);
    }

    public function rules(): array
    {
        return [
            'buyer_id' => ['required', Rule::exists('msfl_buyers', 'id')],
            'season_id' => ['nullable', Rule::exists('msfl_seasons', 'id')],
            'merchandiser_id' => ['nullable', Rule::exists('users', 'id')],
            'factory_id' => ['nullable', Rule::exists('msfl_factories', 'id')],
            'inquiry_id' => ['nullable', Rule::exists('msfl_inquiries', 'id')],
            'buyer_order_ref' => ['nullable', 'string', 'max:100'],
            'order_date' => ['required', 'date'],
            'currency_id' => ['nullable', Rule::exists('msfl_currencies', 'id')],
            'delivery_term' => ['nullable', Rule::in(array_keys(Order::DELIVERY_TERMS))],
            'payment_term_id' => ['nullable', Rule::exists('msfl_com_payment_terms', 'id')->whereNull('deleted_at')],
            'is_quick_entry' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', ...FileUploadService::RULES],

            'size_ids' => [Rule::requiredIf(fn () => ! empty($this->input('pos'))), 'array'],
            'size_ids.*' => ['integer', Rule::exists('inv_sizes', 'id')->whereNull('deleted_at')],
            'pos' => ['array'],
            'pos.*.id' => ['nullable', 'integer'],
            'pos.*.po_no' => ['required', 'string', 'max:100'],
            'pos.*.style_id' => ['required', Rule::exists('msfl_styles', 'id')],
            'pos.*.color_id' => ['required', Rule::exists('inv_colors', 'id')->whereNull('deleted_at')],
            'pos.*.unit_price' => ['required', 'numeric', 'min:0'],
            'pos.*.pcd_date' => ['nullable', 'date'],
            'pos.*.shipment_date' => ['nullable', 'date'],
            'pos.*.ship_mode_id' => ['nullable', Rule::exists('msfl_ship_modes', 'id')],
            'pos.*.remarks' => ['nullable', 'string', 'max:255'],
            ...collect(self::FLAGS)->mapWithKeys(fn ($f) => ["pos.*.{$f}" => ['nullable', 'boolean']])->all(),
            'pos.*.sizes' => ['present', 'array'],
            'pos.*.sizes.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** "Row 2 — PO No" instead of "pos.1.po_no". */
    public function attributes(): array
    {
        $attributes = ['size_ids' => 'Sizes'];
        $names = ['po_no' => 'PO No', 'style_id' => 'Style', 'color_id' => 'Color (set it on the style in Master Data → Styles)', 'unit_price' => 'Unit Price', 'pcd_date' => 'PCD',
            'shipment_date' => 'Shipment Date', 'ship_mode_id' => 'Ship Mode', 'remarks' => 'Remarks', 'sizes' => 'Sizes'];
        foreach (array_keys((array) $this->input('pos', [])) as $i) {
            foreach ($names as $field => $label) {
                $attributes["pos.{$i}.{$field}"] = 'Row ' . ($i + 1) . ' — ' . $label;
            }
        }

        return $attributes;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Order|null $order */
            $order = $this->route('order');
            $existing = $order ? $order->pos()->with(['style', 'color'])->get()->keyBy('id') : collect();
            $styleBuyers = Style::query()->whereIn('id', collect($this->input('pos'))->pluck('style_id'))->pluck('buyer_id', 'id');
            $seen = [];

            foreach ($this->input('pos', []) as $i => $row) {
                $label = 'Row ' . ($i + 1) . ': ';
                if (! empty($row['id']) && ! $existing->has((int) $row['id'])) {
                    $validator->errors()->add("pos.{$i}.id", $label . 'this PO line is not part of the order.');
                }
                if (array_sum(array_map('intval', (array) $row['sizes'])) <= 0) {
                    $validator->errors()->add("pos.{$i}.sizes", $label . 'enter the quantity of at least one size.');
                }
                if ((int) ($styleBuyers[$row['style_id']] ?? 0) !== (int) $this->buyer_id) {
                    $validator->errors()->add("pos.{$i}.style_id", $label . 'the style belongs to a different buyer than this order.');
                }
                if (! empty($row['pcd_date']) && ! empty($row['shipment_date']) && Carbon::parse($row['shipment_date'])->lt(Carbon::parse($row['pcd_date']))) {
                    $validator->errors()->add("pos.{$i}.shipment_date", $label . 'the shipment date is before the PCD.');
                }
                if (! empty($row['id']) && ($existingPo = $existing->get((int) $row['id']))
                    && ($reason = app(OrderPoLines::class)->lockedChange($existingPo, (int) $row['style_id'], (int) $row['color_id'], array_map('intval', (array) $row['sizes'])))) {
                    $validator->errors()->add("pos.{$i}.sizes", $label . $reason);
                }
                $key = mb_strtolower(trim($row['po_no'])) . '|' . $row['style_id'] . '|' . $row['color_id'];
                if (isset($seen[$key])) {
                    $validator->errors()->add("pos.{$i}.po_no", $label . 'the same PO and style as row ' . ($seen[$key] + 1) . '.');
                }
                $seen[$key] = $i;
            }

            // Lines removed from the form must not have work recorded against them.
            $kept = collect($this->input('pos'))->pluck('id')->filter()->map(fn ($id) => (int) $id);
            foreach ($existing->except($kept->all()) as $po) {
                if ($reason = app(OrderPoLines::class)->usedIn($po)) {
                    $validator->errors()->add('pos', $reason);
                }
            }
        }];
    }

    /** Validated PO lines ready for OrderPoLines::sync(): unchecked flags as false. */
    public function poLines(): array
    {
        return array_map(function ($row) {
            $row['sizes'] = $row['sizes'] ?? [];
            foreach (self::FLAGS as $flag) {
                $row[$flag] = (bool) ($row[$flag] ?? false);
            }

            return $row;
        }, $this->validated('pos', []));
    }
}
