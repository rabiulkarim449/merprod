{{-- props: order (model, may be unsaved), buyers, seasons, merchandisers, factories, inquiries, currencies --}}
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'buyer_id', 'label' => 'Buyer', 'required' => true, 'options' => $buyers->pluck('name', 'id'), 'value' => $order->buyer_id])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'order_date', 'label' => 'Order Date', 'type' => 'date', 'required' => true, 'value' => $order->order_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'buyer_order_ref', 'label' => 'Buyer Order / Contract Ref', 'value' => $order->buyer_order_ref])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'season_id', 'label' => 'Season', 'options' => $seasons->pluck('name', 'id'), 'value' => $order->season_id])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'options' => $merchandisers->pluck('name', 'id'), 'value' => $order->merchandiser_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'factory_id', 'label' => 'Factory', 'options' => $factories->pluck('name', 'id'), 'value' => $order->factory_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'inquiry_id', 'label' => 'Inquiry', 'options' => $inquiries->mapWithKeys(fn ($i) => [$i->id => $i->inquiry_no . ($i->style_ref ? ' — ' . $i->style_ref : '')]), 'value' => $order->inquiry_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'currency_id', 'label' => 'Currency', 'options' => $currencies->pluck('code', 'id'), 'value' => $order->currency_id])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'delivery_term', 'label' => 'Delivery Term', 'options' => \ME\MerchandisingSfl\Models\Order::DELIVERY_TERMS, 'value' => $order->delivery_term])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'payment_term_id', 'label' => 'Payment Term', 'options' => \ME\MerchandisingSfl\Models\Commercial\PaymentTerm::query()->active()->orderBy('days')->orderBy('name')->pluck('name', 'id'), 'value' => $order->payment_term_id])
    <div class="col-md-3 mb-3">
        <label class="form-label">Attachment (contract / PO sheet)</label>
        <input type="file" name="attachment" class="form-control form-control-sm">
        @if($order->attachment)
            <small><a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($order->attachment) }}" target="_blank">Current file</a> — upload to replace</small>
        @endif
    </div>
    <div class="col-md-3 mb-3 d-flex align-items-end">
        <div class="custom-control custom-switch">
            <input type="hidden" name="is_quick_entry" value="0">
            <input type="checkbox" name="is_quick_entry" value="1" class="custom-control-input" id="orderQuickEntry" @checked(old('is_quick_entry', $order->is_quick_entry))>
            <label class="custom-control-label" for="orderQuickEntry">Quick Entry <small class="text-muted d-block">(পুরনো/legacy order — Bulletin/T&amp;A/BOM ছাড়াই সরাসরি Production-এ)</small></label>
        </div>
    </div>
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 12, 'value' => $order->remarks])
</div>

{{-- Inquiry picked → buyer, season, merchandiser, factory; buyer → its terms. --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'inquiry_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::inquiries(), 'fields' => ['buyer_id' => 'buyer_id', 'season_id' => 'season_id', 'merchandiser_id' => 'merchandiser_id', 'factory_id' => 'factory_id']])
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'buyer_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::buyers(), 'fields' => ['merchandiser_id' => 'merchandiser_id', 'delivery_term' => 'delivery_term', 'payment_term_id' => 'payment_term_id']])
