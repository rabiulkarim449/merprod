{{-- props: pos, selected (id|null), required (bool, default true), name (default order_po_id), col (default 6), filter (bool: no label, "All POs"), id (default poSelect)
     Shown as style · color — buyer (no order / PO no); the shipment date is added only when the same style + color repeats. --}}
@php
    $name = $name ?? 'order_po_id';
    $filter = $filter ?? false;
    $key = fn ($po) => ($po->style->style_no ?? '') . '|' . ($po->color->name ?? '') . '|' . ($po->order->buyer->name ?? '');
    $repeated = $pos->countBy($key)->filter(fn ($n) => $n > 1);
@endphp
<div class="col-md-{{ $col ?? 6 }} mb-{{ $filter ? 2 : 3 }}">
    @unless($filter)<label class="form-label">Style · Color <span class="text-danger">*</span> <small class="text-muted">(buyer, style, color come from it)</small></label>@endunless
    <select name="{{ $name }}" id="{{ $id ?? 'poSelect' }}" class="form-control form-control-sm msfl-select2" @required(! $filter && ($required ?? true))>
        <option value="">{{ $filter ? 'All styles' : '— Select style —' }}</option>
        @foreach($pos as $po)
            <option value="{{ $po->id }}" @selected((string) old($name, $selected ?? request($name)) === (string) $po->id)>{{ $po->style->style_no ?? '-' }} · {{ $po->color->name ?? '-' }} — {{ $po->order->buyer->name ?? '' }} ({{ number_format($po->po_qty) }} pcs){{ $repeated->has($key($po)) && $po->shipment_date ? ' · ship ' . $po->shipment_date->format('d-M') : '' }}</option>
        @endforeach
    </select>
</div>
