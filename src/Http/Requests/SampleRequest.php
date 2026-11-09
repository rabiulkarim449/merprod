<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Services\FileUploadService;

class SampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('sample') ? 'msfl_sample.edit' : 'msfl_sample.add');
    }

    public function rules(): array
    {
        return [
            'style_id' => ['required', Rule::exists('msfl_styles', 'id')],
            'sample_type_id' => ['required', Rule::exists('msfl_sample_types', 'id')],
            'order_id' => ['nullable', Rule::exists('msfl_orders', 'id')],
            'merchandiser_id' => ['nullable', Rule::exists('users', 'id')],
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'qty' => ['required', 'integer', 'min:1'],
            'size_ref' => ['nullable', 'string', 'max:100'],
            'color_ref' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', ...FileUploadService::RULES],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty() && $this->filled('order_id')
                && Order::whereKey($this->order_id)->value('buyer_id') != Style::whereKey($this->style_id)->value('buyer_id')) {
                $validator->errors()->add('order_id', 'The selected order belongs to a different buyer than the style.');
            }
        }];
    }
}
