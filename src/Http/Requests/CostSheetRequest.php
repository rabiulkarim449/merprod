<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models\CostSheet;

class CostSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('cost_sheet') ? 'msfl_cost_sheet.edit' : 'msfl_cost_sheet.add');
    }

    /** Drop untouched blank rows the form always renders. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'items' => collect($this->input('items', []))
                ->filter(fn ($line) => filled($line['item_id'] ?? null) || filled($line['description'] ?? null))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'buyer_id' => ['required', Rule::exists('msfl_buyers', 'id')],
            'style_id' => ['nullable', Rule::exists('msfl_styles', 'id')],
            'inquiry_id' => ['nullable', Rule::exists('msfl_inquiries', 'id')],
            'style_ref' => ['nullable', 'string', 'max:150'],
            'garment_description' => ['nullable', 'string', 'max:255'],
            'size_range' => ['nullable', 'string', 'max:100'],
            'currency_id' => ['nullable', Rule::exists('msfl_currencies', 'id')],
            'costing_date' => ['required', 'date'],
            'order_qty' => ['nullable', 'integer', 'min:0'],
            'smv' => ['nullable', 'numeric', 'min:0'],
            'price_type' => ['nullable', Rule::in(array_keys(CostSheet::PRICE_TYPES))],
            'cm_minute_rate' => ['nullable', 'numeric', 'min:0'],
            'efficiency_percent' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'cm_cost' => ['nullable', 'numeric', 'min:0'], // empty: SMV × CPM ÷ efficiency (controller)
            'commercial_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'other_cost' => ['required', 'numeric', 'min:0'],
            'profit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'buyer_target_price' => ['nullable', 'numeric', 'min:0'],
            'final_price' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.group' => ['required', Rule::in(array_keys(CostSheet::GROUPS))],
            'items.*.item_id' => ['nullable', Rule::exists('msfl_items', 'id')],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.supplier_name' => ['nullable', 'string', 'max:150'],
            'items.*.uom_id' => ['nullable', Rule::exists('inv_units', 'id')->whereNull('deleted_at')],
            'items.*.consumption' => ['required', 'numeric', 'min:0'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Add at least one cost line (fabric, accessories, wash …).'];
    }
}
