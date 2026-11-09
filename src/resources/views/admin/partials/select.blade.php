{{-- props: name, label, options ([value => label]), value, required (bool), col (default 3), attrs (string), placeholder --}}
<div class="col-md-{{ $col ?? 3 }} mb-3">
    <label class="form-label">{{ $label }} @if($required ?? false)<span class="text-danger">*</span>@endif</label>
    <select name="{{ $name }}" class="form-control form-control-sm msfl-select2" @required($required ?? false) {!! $attrs ?? '' !!}>
        <option value="">{{ $placeholder ?? '— Select —' }}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
