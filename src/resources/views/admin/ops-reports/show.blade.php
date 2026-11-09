@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle($report->title()) }}</title>
@endsection

@section('contents')
@php $filters = $report->filters(); @endphp
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">{{ $report->title() }}</h4>
            <div class="d-flex gap-1">
                <a href="{{ route('msfl.ops-reports.print', ['report' => $report->key()] + request()->query()) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
                <a href="{{ route('msfl.ops-reports.print', ['report' => $report->key(), 'blank' => 1] + request()->query()) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Empty template with 5 blank rows"><i class="fa-solid fa-print"></i> Empty template with 5 blank rows</a>
                <a href="{{ route('msfl.ops-reports.index') }}" class="btn btn-sm ui-act ui-act-back" title="Reports"><i class="fa-solid fa-arrow-left"></i> Reports</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-2 align-items-end">
                <div class="col-md-2 mb-2"><label class="form-label small mb-0">Reporting Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="{{ request('date', today()->toDateString()) }}"></div>
                @if(in_array('range', $filters, true))
                    <div class="col-md-2 mb-2"><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}"></div>
                    <div class="col-md-2 mb-2"><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}"></div>
                @endif
                @if(in_array('buyer', $filters, true))
                    <div class="col-md-2 mb-2"><label class="form-label small mb-0">Buyer</label><select name="buyer_id" class="form-control form-control-sm msfl-select2"><option value="">All Buyers</option>
                        @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
                @endif
                @if(in_array('style', $filters, true))
                    <div class="col-md-2 mb-2"><label class="form-label small mb-0">Style</label><select name="style_id" class="form-control form-control-sm msfl-select2"><option value="">All Styles</option>
                        @foreach($styles as $st)<option value="{{ $st->id }}" @selected(request('style_id') == $st->id)>{{ $st->style_no }}</option>@endforeach</select></div>
                @endif
                @if(in_array('line', $filters, true))
                    <div class="col-md-2 mb-2"><label class="form-label small mb-0">Line</label><select name="line_id" class="form-control form-control-sm msfl-select2"><option value="">All Lines</option>
                        @foreach($lines as $l)<option value="{{ $l->id }}" @selected(request('line_id') == $l->id)>{{ $l->name }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-2 mb-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.ops-reports.show', $report->key()) }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <form method="POST" action="{{ route('msfl.ops-reports.save', ['report' => $report->key()] + request()->query()) }}" id="opsForm">
                @csrf
                @include('merchandising-sfl::admin.ops-reports.partials.sheet', ['editable' => $canEdit, 'print' => false])
                @if($canEdit && count($built['rows']) + count($report->settingsShown()))
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save changes"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                        <span class="small text-muted" id="opsChanged">No changes yet.</span>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

@push('js')
<script>
(function () {
    const form = document.getElementById('opsForm');
    if (! form) return;
    const fields = () => form.querySelectorAll('[data-orig]');
    const isChanged = el => (el.value ?? '') !== (el.dataset.orig ?? '');
    function mark() {
        let n = 0;
        fields().forEach(el => { const c = isChanged(el); el.classList.toggle('changed', c); if (c) n++; });
        const out = document.getElementById('opsChanged');
        if (out) out.textContent = n ? n + ' cell(s) changed — Save to keep them.' : 'No changes yet.';
    }
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    // Send only what changed — a big report has more cells than PHP accepts in one form (max_input_vars).
    form.addEventListener('submit', function () {
        fields().forEach(el => { if (! isChanged(el)) el.disabled = true; });
    });
    mark();
})();
</script>
@endpush
