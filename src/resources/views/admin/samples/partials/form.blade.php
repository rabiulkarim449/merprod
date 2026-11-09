{{-- props: sample (model, may be unsaved), styles, sampleTypes, orders, merchandisers --}}
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'style_id', 'label' => 'Style', 'required' => true, 'options' => $styles->mapWithKeys(fn ($s) => [$s->id => $s->label()]), 'value' => $sample->style_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'sample_type_id', 'label' => 'Sample Type / Stage', 'required' => true, 'options' => $sampleTypes->pluck('name', 'id'), 'value' => $sample->sample_type_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'order_id', 'label' => 'Order (if confirmed)', 'options' => $orders->mapWithKeys(fn ($o) => [$o->id => $o->order_no . ($o->buyer_order_ref ? ' — ' . $o->buyer_order_ref : '')]), 'value' => $sample->order_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'options' => $merchandisers->pluck('name', 'id'), 'value' => $sample->merchandiser_id])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'request_date', 'label' => 'Request Date', 'type' => 'date', 'required' => true, 'value' => $sample->request_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'required_date', 'label' => 'Required (Due) Date', 'type' => 'date', 'value' => $sample->required_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'qty', 'label' => 'Qty (pcs)', 'type' => 'number', 'required' => true, 'value' => $sample->qty])
    <div class="col-md-3 mb-3">
        <label class="form-label">Attachment</label>
        <input type="file" name="attachment" class="form-control form-control-sm">
        @if($sample->attachment)
            <small><a href="{{ $sample->attachmentUrl() }}" target="_blank">Current file</a> — upload to replace</small>
        @endif
    </div>

    @include('merchandising-sfl::admin.partials.input', ['name' => 'size_ref', 'label' => 'Size(s)', 'value' => $sample->size_ref])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'color_ref', 'label' => 'Color(s)', 'value' => $sample->color_ref])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks / Instructions', 'type' => 'textarea', 'col' => 6, 'value' => $sample->remarks])
</div>

{{-- Style picked → merchandiser, its order, colors and sizes of its POs. --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'style_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::styles(), 'fields' => ['merchandiser_id' => 'merchandiser_id', 'order_id' => 'order_id', 'color_ref' => 'color_ref', 'size_ref' => 'size_ref']])
