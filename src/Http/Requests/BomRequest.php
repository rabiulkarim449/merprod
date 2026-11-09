<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Bom;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\FileUploadService;

class BomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('bom') ? 'msfl_bom.edit' : 'msfl_bom.add');
    }

    /** Drop untouched blank rows the form always renders. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'items' => collect($this->input('items', []))->filter(fn ($line) => filled($line['item_id'] ?? null))->values()->all(),
        ]);
    }

    public function rules(): array
    {
        $existingFile = $this->route('bom')?->bom_file;

        return [
            'style_id' => ['required', Rule::exists('msfl_styles', 'id')],
            'order_id' => ['nullable', Rule::exists('msfl_orders', 'id')],
            'bom_type' => ['required', Rule::in(array_keys(Bom::TYPES))],
            'bom_file' => [$existingFile ? 'nullable' : 'required_if:bom_type,file', ...FileUploadService::RULES],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'items' => ['required_if:bom_type,manual', 'array'],
            'items.*.item_id' => ['required', Rule::exists('msfl_items', 'id')],
            'items.*.color_id' => ['nullable', Rule::exists('inv_colors', 'id')->whereNull('deleted_at')],
            'items.*.size_id' => ['nullable', Rule::exists('inv_sizes', 'id')->whereNull('deleted_at')],
            'items.*.placement' => ['nullable', 'string', 'max:255'],
            'items.*.consumption' => ['required', 'numeric', 'gt:0'],
            'items.*.uom_id' => ['nullable', Rule::exists('inv_units', 'id')->whereNull('deleted_at')],
            'items.*.wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.supplier_id' => ['nullable', Rule::exists('inv_suppliers', 'id')->whereNull('deleted_at')],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required_if' => 'Add at least one item line, or choose "Buyer File" and upload the BOM.',
            'bom_file.required_if' => 'Upload the buyer\'s BOM file.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty() && $this->filled('order_id')
                && ! OrderPo::where('order_id', $this->order_id)->where('style_id', $this->style_id)->exists()) {
                $validator->errors()->add('order_id', 'The selected order has no PO line for this style.');
            }
        }];
    }
}
