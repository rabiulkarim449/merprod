{{-- props: definition (MasterRegistry entry), record (optional, for edit) --}}
@php
    $idPrefix = $definition['slug'] . ($record->id ?? 'new');
@endphp
<div class="row">
    @foreach($definition['fields'] as $field)
        @php
            $name = $field['name'];
            $value = old($name, $record?->{$name} ?? ($field['default'] ?? null));
            if ($value instanceof \Carbon\CarbonInterface) {
                $value = $value->format('Y-m-d');
            }
        @endphp
        <div class="col-md-{{ $field['col'] ?? ($field['type'] === 'boolean' ? 3 : 4) }} mb-3 {{ $field['type'] === 'boolean' ? 'd-flex align-items-center' : '' }}">
            @if($field['type'] === 'boolean')
                <div class="custom-control custom-switch">
                    <input type="hidden" name="{{ $name }}" value="0">
                    <input type="checkbox" name="{{ $name }}" value="1" class="custom-control-input" id="{{ $idPrefix }}_{{ $name }}" @checked($value)>
                    <label class="custom-control-label" for="{{ $idPrefix }}_{{ $name }}">{{ $field['label'] }}</label>
                </div>
            @else
                <label class="form-label">{{ $field['label'] }} @if(! empty($field['required']))<span class="text-danger">*</span>@endif</label>
                @switch($field['type'])
                    @case('select')
                        <select name="{{ $name }}" class="form-control form-control-sm msfl-select2" @required(! empty($field['required']))>
                            <option value="">— Select —</option>
                            @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                        @break
                    @case('textarea')
                        <textarea name="{{ $name }}" rows="2" class="form-control form-control-sm" @required(! empty($field['required']))>{{ $value }}</textarea>
                        @break
                    @default
                        <input
                            type="{{ ['decimal' => 'number', 'number' => 'number', 'date' => 'date', 'email' => 'email'][$field['type']] ?? 'text' }}"
                            @if($field['type'] === 'decimal') step="any" min="0" @endif
                            @if($field['type'] === 'number') step="1" min="{{ $field['min'] ?? 0 }}" @endif
                            name="{{ $name }}" class="form-control form-control-sm" value="{{ $value }}" @required(! empty($field['required']))>
                @endswitch
            @endif
        </div>
    @endforeach
    <div class="col-md-3 mb-3 d-flex align-items-center">
        <div class="custom-control custom-switch">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="{{ $idPrefix }}_is_active" @checked(old('is_active', $record->is_active ?? true))>
            <label class="custom-control-label" for="{{ $idPrefix }}_is_active">Active</label>
        </div>
    </div>
</div>
