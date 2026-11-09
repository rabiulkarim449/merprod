{{-- props: name, label, value, type (default text), required (bool), col (default 3), attrs (string) --}}
@php
    $inputValue = old($name, $value ?? '');
    if ($inputValue instanceof \Carbon\CarbonInterface) {
        $inputValue = $inputValue->format('Y-m-d');
    }
@endphp
<div class="col-md-{{ $col ?? 3 }} mb-3">
    <label class="form-label">{{ $label }} @if($required ?? false)<span class="text-danger">*</span>@endif</label>
    @if(($type ?? 'text') === 'textarea')
        <textarea name="{{ $name }}" rows="{{ $rows ?? 2 }}" class="form-control form-control-sm" @required($required ?? false) {!! $attrs ?? '' !!}>{{ $inputValue }}</textarea>
    @else
        <input type="{{ $type ?? 'text' }}" name="{{ $name }}" class="form-control form-control-sm" value="{{ $inputValue }}"
            @if(($type ?? 'text') === 'number') step="any" min="0" @endif
            @required($required ?? false) {!! $attrs ?? '' !!}>
    @endif
</div>
