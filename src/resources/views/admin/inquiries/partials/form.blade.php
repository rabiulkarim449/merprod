{{-- props: inquiry (nullable), buyers, seasons, merchandisers, factories, productTypes --}}
<div class="row">
    @include('merchandising-sfl::admin.partials.input', ['name' => 'inquiry_date', 'label' => 'Inquiry Date', 'type' => 'date', 'required' => true, 'value' => $inquiry->inquiry_date ?? now()])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'buyer_id', 'label' => 'Buyer', 'required' => true, 'options' => $buyers->pluck('name', 'id'), 'value' => $inquiry->buyer_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'season_id', 'label' => 'Season', 'options' => $seasons->pluck('name', 'id'), 'value' => $inquiry->season_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'options' => $merchandisers->pluck('name', 'id'), 'value' => $inquiry->merchandiser_id ?? auth()->id()])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'style_ref', 'label' => 'Style Ref / Name', 'value' => $inquiry->style_ref ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'product_type_id', 'label' => 'Product Type', 'options' => $productTypes->pluck('name', 'id'), 'value' => $inquiry->product_type_id ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'color_ref', 'label' => 'Color(s)', 'value' => $inquiry->color_ref ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'factory_id', 'label' => 'Allocated Factory', 'options' => $factories->pluck('name', 'id'), 'value' => $inquiry->factory_id ?? null])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'order_qty', 'label' => 'Order Qty (pcs)', 'type' => 'number', 'value' => $inquiry->order_qty ?? null, 'attrs' => 'data-inquiry-qty'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'unit_price', 'label' => 'Unit Price', 'type' => 'number', 'value' => $inquiry->unit_price ?? null, 'attrs' => 'data-inquiry-price'])
    <div class="col-md-3 mb-3">
        <label class="form-label">Total Value</label>
        <input type="text" class="form-control form-control-sm" data-inquiry-total readonly>
    </div>
    @include('merchandising-sfl::admin.partials.select', ['name' => 'status', 'label' => 'Status', 'required' => true, 'placeholder' => '— Select —', 'options' => \ME\MerchandisingSfl\Models\Inquiry::statusOptions(), 'value' => $inquiry->status ?? 'open'])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'confirmation_due_date', 'label' => 'Order Confirmation Due', 'type' => 'date', 'value' => $inquiry->confirmation_due_date ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'target_ship_date', 'label' => 'Target Ship Date', 'type' => 'date', 'value' => $inquiry->target_ship_date ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'extended_ship_date', 'label' => 'Extended Ship Date', 'type' => 'date', 'value' => $inquiry->extended_ship_date ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'lost_reason', 'label' => 'Lost Reason (if lost)', 'value' => $inquiry->lost_reason ?? null])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'col' => 6, 'value' => $inquiry->description ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 6, 'value' => $inquiry->remarks ?? null])
</div>

@push('js')
<script>
    (function () {
        const qty = document.querySelector('[data-inquiry-qty]');
        const price = document.querySelector('[data-inquiry-price]');
        const total = document.querySelector('[data-inquiry-total]');
        function recalc() {
            const value = (parseFloat(qty.value) || 0) * (parseFloat(price.value) || 0);
            total.value = value ? value.toFixed(2) : '';
        }
        qty.addEventListener('input', recalc);
        price.addEventListener('input', recalc);
        recalc();
    })();
</script>
@endpush

{{-- Buyer picked → its merchandiser. --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'buyer_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::buyers(), 'fields' => ['merchandiser_id' => 'merchandiser_id']])
