{{--
    props: style (nullable), masterStyles + pickedStyleId (new only), inquiries, seasons,
           merchandisers, productTypes, washTypes
    Style No / Name / Buyer come from Master Data → Styles: a new tech pack picks
    one of those styles; on edit they are shown read-only.
--}}
@php $isNew = ! ($style?->exists); @endphp
<div class="row">
    @if($isNew)
        <div class="col-md-6 mb-3">
            <label class="form-label">Style <span class="text-danger">*</span> <small class="text-muted">(Master Data → Styles)</small></label>
            <select name="style_id" id="techPackStyle" class="form-control form-control-sm msfl-select2" required>
                <option value="">— Select style —</option>
                @foreach($masterStyles as $s)
                    <option value="{{ $s->id }}" data-season="{{ $s->season_id }}" data-product-type="{{ $s->product_type_id }}"
                        @selected((string) old('style_id', $pickedStyleId ?? '') === (string) $s->id)>{{ $s->style_no }} — {{ $s->name }} ({{ $s->buyer->name ?? '-' }})</option>
                @endforeach
            </select>
            <small class="text-muted">
                Only styles without a tech pack.
                @can('msfl_style.add')
                    New style? Add it in <a href="{{ route('msfl.masters.index', 'styles') }}" target="_blank">Master Data → Styles</a>.
                @endcan
            </small>
        </div>
    @else
        <div class="col-md-3 mb-3">
            <label class="form-label">Style No</label>
            <input type="text" class="form-control form-control-sm" value="{{ $style->style_no }}" readonly>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Style Name</label>
            <input type="text" class="form-control form-control-sm" value="{{ $style->name }}" readonly>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label">Buyer</label>
            <input type="text" class="form-control form-control-sm" value="{{ $style->buyer->name ?? '-' }}" readonly>
            @can('msfl_style.edit')
                <small class="text-muted">Change in <a href="{{ route('msfl.masters.index', ['styles', 'search' => $style->style_no]) }}" target="_blank">Master Data → Styles</a></small>
            @endcan
        </div>
    @endif
    @include('merchandising-sfl::admin.partials.select', ['name' => 'inquiry_id', 'label' => 'Inquiry', 'options' => $inquiries->mapWithKeys(fn ($i) => [$i->id => $i->inquiry_no . ($i->style_ref ? ' — ' . $i->style_ref : '')]), 'value' => $style->inquiry_id ?? null])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'season_id', 'label' => 'Season', 'options' => $seasons->pluck('name', 'id'), 'value' => $style->season_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'options' => $merchandisers->pluck('name', 'id'), 'value' => $style->merchandiser_id ?? auth()->id()])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'product_type_id', 'label' => 'Product Type', 'options' => $productTypes->pluck('name', 'id'), 'value' => $style->product_type_id ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'wash_type_id', 'label' => 'Wash Type', 'options' => $washTypes->pluck('name', 'id'), 'value' => $style->wash_type_id ?? null])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'smv', 'label' => 'SMV', 'type' => 'number', 'value' => $style->smv ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'target_cm', 'label' => 'Target CM (per dozen)', 'type' => 'number', 'value' => $style->target_cm ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'confirm_cm', 'label' => 'Confirm CM (per dozen)', 'type' => 'number', 'value' => $style->confirm_cm ?? null])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'fabric_sourced_by', 'label' => 'Fabric Sourced By', 'required' => true, 'options' => ['self' => 'Self (Factory)', 'buyer' => 'Buyer (Nominated / Supplied)'], 'value' => $style->fabric_sourced_by ?? 'self'])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'development_status', 'label' => 'Development Status', 'required' => true, 'options' => \ME\MerchandisingSfl\Models\Style::statusOptions(), 'value' => $style->development_status ?? 'new'])
    @foreach(['tech_pack_file' => 'Tech Pack', 'artwork_file' => 'Artwork', 'size_chart_file' => 'Size Chart'] as $file => $fileLabel)
        <div class="col-md-3 mb-3">
            <label class="form-label">{{ $fileLabel }} File</label>
            <input type="file" name="{{ $file }}" class="form-control form-control-sm">
            @if($style?->{$file})
                <small><a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($style->{$file}) }}" target="_blank">Current file</a> — upload to replace</small>
            @endif
        </div>
    @endforeach

    @include('merchandising-sfl::admin.partials.input', ['name' => 'fabric_description', 'label' => 'Fabric Description', 'type' => 'textarea', 'col' => 6, 'value' => $style->fabric_description ?? null])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'description', 'label' => 'Style Description / Construction', 'type' => 'textarea', 'col' => 6, 'value' => $style->description ?? null])

    <div class="col-md-3 mb-3 d-flex align-items-center"><div class="custom-control custom-switch">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="styleActive" @checked(old('is_active', $style->is_active ?? true))>
        <label class="custom-control-label" for="styleActive">Active</label>
    </div></div>
</div>

{{-- Inquiry picked → season, merchandiser, product type, description. --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'inquiry_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::inquiries(), 'fields' => ['season_id' => 'season_id', 'merchandiser_id' => 'merchandiser_id', 'product_type_id' => 'product_type_id', 'description' => 'description', 'smv' => 'smv']])
