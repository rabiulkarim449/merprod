<?php

namespace ME\MerchandisingSfl\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Services\Commercial\LcStatus;
use ME\MerchandisingSfl\Services\FileUploadService;

/** Export LC / Sales Contract header + the PO lines it covers (po_ids[]). */
class ExportLcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('export_lc') ? 'msfl_export_lc.edit' : 'msfl_export_lc.add');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(ExportLc::TYPES))],
            'buyer_lc_no' => ['required', 'string', 'max:100'],
            'buyer_id' => ['required', Rule::exists('msfl_buyers', 'id')],
            'currency_id' => ['nullable', Rule::exists('msfl_currencies', 'id')],
            'lc_date' => ['required', 'date'],
            'last_shipment_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:last_shipment_date'],
            'lc_value' => ['required', 'numeric', 'gt:0'],
            'tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'payment_term_id' => ['required', Rule::exists('msfl_com_payment_terms', 'id')->whereNull('deleted_at')],
            'issuing_bank_id' => ['nullable', Rule::exists('msfl_com_banks', 'id')->whereNull('deleted_at')],
            'lien_bank_id' => ['nullable', Rule::exists('msfl_com_banks', 'id')->whereNull('deleted_at')],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'array', 'max:20'],
            'attachment.*' => FileUploadService::RULES,
            'remove_attachment' => ['nullable', 'array'],
            'remove_attachment.*' => ['string'],
            'amendment_remarks' => ['nullable', 'string', 'max:255'],
            'po_ids' => ['nullable', 'array'],
            'po_ids.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return ['buyer_lc_no' => 'LC / SC No', 'po_ids' => 'POs', 'payment_term_id' => 'Payment Term', 'issuing_bank_id' => 'Issuing Bank', 'lien_bank_id' => 'Lien Bank'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            /** @var ExportLc|null $lc */
            $lc = $this->route('export_lc');
            $picked = collect($this->input('po_ids', []))->map(fn ($id) => (int) $id)->unique();
            $allowed = app(LcStatus::class)->selectablePos((int) $this->buyer_id, $lc)->pluck('id');

            if ($picked->diff($allowed)->isNotEmpty()) {
                $validator->errors()->add('po_ids', 'A picked PO is not a confirmed PO of this buyer, or it is already on another LC.');
            }
            if ($lc) {
                $removed = $lc->pos()->pluck('msfl_order_pos.id')->diff($picked);
                $invoiced = DB::table('msfl_com_invoice_lines as l')->join('msfl_com_invoices as i', 'i.id', '=', 'l.invoice_id')
                    ->whereNull('i.deleted_at')->where('i.export_lc_id', $lc->id)->whereIn('l.order_po_id', $removed)->exists();
                if ($invoiced) {
                    $validator->errors()->add('po_ids', 'A PO already shipped on a commercial invoice of this LC cannot be removed.');
                }
                if ((int) $this->buyer_id !== (int) $lc->buyer_id && $lc->invoices()->exists()) {
                    $validator->errors()->add('buyer_id', 'The buyer cannot change once invoices are made on this LC.');
                }
            }
        }];
    }
}
