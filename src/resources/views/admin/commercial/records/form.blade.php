@php
    $slug = $definition['slug'];
    $heading = $model->exists ? 'Edit ' . $definition['singular'] : 'New ' . $definition['singular'];
    $back = $model->exists ? route("msfl.commercial.$slug.show", $model->id) : route("msfl.commercial.$slug.index");
@endphp
@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle($heading) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $heading }}</h4>
            <a href="{{ $back }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $model->exists ? route("msfl.commercial.$slug.update", $model->id) : route("msfl.commercial.$slug.store") }}" enctype="multipart/form-data">
                @csrf
                @if($model->exists) @method('PUT') @endif
                <div class="row">
                    @foreach($definition['fields'] as $field)
                        @php $options = in_array($field['type'], ['select', 'pick'], true) ? \ME\MerchandisingSfl\Support\CommercialRecordRegistry::options($field, $model) : []; @endphp
                        @switch($field['type'])
                            @case('select')
                                @include('merchandising-sfl::admin.partials.select', ['name' => $field['name'], 'label' => $field['label'], 'required' => $field['required'] ?? false, 'options' => $options, 'value' => $model->{$field['name']}, 'col' => $field['col'] ?? 3])
                                @break
                            @case('pick')
                                @php $picked = collect(old($field['name'], ($field['value'])($model)))->map(fn ($id) => (int) $id)->all(); @endphp
                                <div class="col-md-{{ $field['col'] ?? 12 }} mb-3">
                                    <label class="form-label">{{ $field['label'] }}</label>
                                    <select name="{{ $field['name'] }}[]" class="form-control form-control-sm msfl-select2" multiple>
                                        @foreach($options as $value => $label)<option value="{{ $value }}" @selected(in_array((int) $value, $picked, true))>{{ $label }}</option>@endforeach
                                    </select>
                                    @if(empty($options))<small class="text-muted">No open {{ strtolower($field['label']) }} — enter them first.</small>@endif
                                </div>
                                @break
                            @case('file')
                                @include('merchandising-sfl::admin.commercial.partials.attachments-input', ['name' => $field['name'], 'label' => $field['label'], 'value' => $model->{$field['name']}, 'col' => $field['col'] ?? 6])
                                @break
                            @default
                                @include('merchandising-sfl::admin.partials.input', [
                                    'name' => $field['name'], 'label' => $field['label'], 'required' => $field['required'] ?? false, 'col' => $field['col'] ?? 3,
                                    'type' => match ($field['type']) { 'decimal' => 'number', 'date' => 'date', 'textarea' => 'textarea', default => 'text' },
                                    'value' => $model->{$field['name']} ?? ($field['type'] === 'date' && ! empty($field['required']) && ! $model->exists ? now() : null),
                                ])
                        @endswitch
                    @endforeach
                </div>
                <button type="submit" class="btn mt-3 btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="{{ $back }}" class="btn mt-3 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
