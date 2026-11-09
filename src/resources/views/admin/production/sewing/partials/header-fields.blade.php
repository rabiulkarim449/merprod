{{-- Date, Line, Style · Color of a sewing modal. props: prefix (DOM ids), pos, lines, date (Carbon), mine (bool: this modal's old input) --}}
<div class="col-md-3 mb-3">
    <label class="form-label">Date <span class="text-danger">*</span></label>
    <input type="date" name="entry_date" id="{{ $prefix }}Date" class="form-control form-control-sm" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" required>
</div>
<div class="col-md-3 mb-3">
    <label class="form-label">Line <span class="text-danger">*</span></label>
    <select name="line_id" id="{{ $prefix }}Line" class="form-control form-control-sm msfl-select2" required>
        <option value="">— Select line —</option>
        @foreach($lines as $line)<option value="{{ $line->id }}" @selected($mine && (string) old('line_id') === (string) $line->id)>{{ $line->name }}</option>@endforeach
    </select>
    @if($lines->isEmpty())<span class="form-text text-danger">No line set up — Planning → Setup → Lines.</span>@endif
</div>
@include('merchandising-sfl::admin.production.partials.po-select', ['id' => $prefix . 'Po', 'selected' => $mine ? old('order_po_id') : '', 'col' => 6])
