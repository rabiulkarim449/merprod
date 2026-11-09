<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Style;

class OrderPoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('msfl_order.edit');
    }

    /** A style with a color gives it to the line (one color per style). */
    protected function prepareForValidation(): void
    {
        if ($color = Style::whereKey($this->input('style_id'))->value('color_id')) {
            $this->merge(['color_id' => $color]);
        }
    }

    public function rules(): array
    {
        return [
            'style_id' => ['required', Rule::exists('msfl_styles', 'id')],
            'color_id' => ['required', Rule::exists('inv_colors', 'id')->whereNull('deleted_at')],
            'po_no' => ['required', 'string', 'max:100'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'pcd_date' => ['nullable', 'date'],
            'shipment_date' => ['nullable', 'date', 'after_or_equal:pcd_date'],
            'ship_mode_id' => ['nullable', Rule::exists('msfl_ship_modes', 'id')],
            'remarks' => ['nullable', 'string', 'max:255'],
            'needs_embroidery' => ['nullable', 'boolean'],
            'needs_washing' => ['nullable', 'boolean'],
            'applique_ih' => ['nullable', 'boolean'],
            'studs_stones_ih' => ['nullable', 'boolean'],
            'heat_seal_ih' => ['nullable', 'boolean'],
            'sizes' => ['required', 'array'],
            'sizes.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Order $order */
            $order = $this->route('order');
            $po = $this->route('po');

            if (array_sum(array_map('intval', $this->input('sizes', []))) <= 0) {
                $validator->errors()->add('sizes', 'Enter the quantity of at least one size.');
            }

            if (Style::whereKey($this->style_id)->value('buyer_id') != $order->buyer_id) {
                $validator->errors()->add('style_id', 'The style belongs to a different buyer than this order.');
            }

            $duplicate = $order->pos()
                ->where(['po_no' => $this->po_no, 'style_id' => $this->style_id, 'color_id' => $this->color_id])
                ->when($po, fn ($q) => $q->whereKeyNot($po->id))
                ->exists();
            if ($duplicate) {
                $validator->errors()->add('po_no', 'This PO already has a line for the same style and color.');
            }

            if ($po && ($reason = app(\ME\MerchandisingSfl\Services\OrderPoLines::class)->lockedChange($po, (int) $this->style_id, (int) $this->color_id, array_map('intval', $this->input('sizes', []))))) {
                $validator->errors()->add('sizes', $reason);
            }
        }];
    }
}
