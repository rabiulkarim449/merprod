<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models\Inquiry;

class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('inquiry') ? 'msfl_inquiry.edit' : 'msfl_inquiry.add');
    }

    public function rules(): array
    {
        return [
            'inquiry_date' => ['required', 'date'],
            'buyer_id' => ['required', Rule::exists('msfl_buyers', 'id')],
            'season_id' => ['nullable', Rule::exists('msfl_seasons', 'id')],
            'merchandiser_id' => ['nullable', Rule::exists('users', 'id')],
            'factory_id' => ['nullable', Rule::exists('msfl_factories', 'id')],
            'product_type_id' => ['nullable', Rule::exists('msfl_product_types', 'id')],
            'style_ref' => ['nullable', 'string', 'max:150'],
            'color_ref' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'order_qty' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'confirmation_due_date' => ['nullable', 'date'],
            'target_ship_date' => ['nullable', 'date'],
            'extended_ship_date' => ['nullable', 'date', 'after_or_equal:target_ship_date'],
            'status' => ['required', Rule::in(array_keys(Inquiry::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
