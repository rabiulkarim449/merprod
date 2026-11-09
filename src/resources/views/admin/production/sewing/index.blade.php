@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Sewing — Daily Production') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">Sewing — Daily Production <small class="text-muted">{{ $date->format('d M Y') }}</small></h4>
            <div>
                @can('msfl_prod_entry.add')
                    <button type="button" class="btn btn-sm ui-act ui-act-line-input" data-sew-modal="input" title="Line Input"><i class="fa-solid fa-arrow-right-to-bracket"></i> Line Input</button>
                    <button type="button" class="btn btn-sm ui-act ui-act-hourly-output" data-sew-modal="hourly" title="Hourly Output"><i class="fa-solid fa-clock"></i> Hourly Output</button>
                @endcan
                <a href="{{ route('msfl.production.sewing.entries', ['from' => $date->toDateString(), 'to' => $date->toDateString()]) }}" class="btn btn-sm ui-act ui-act-nav" title="Entries"><i class="fa-solid fa-list"></i> Entries</a>
                <a href="{{ route('msfl.production.hourly-report.index', request()->only('date', 'line_id')) }}" class="btn btn-sm ui-act ui-act-report" title="Hourly Report"><i class="fa-solid fa-chart-column"></i> Hourly Report</a>
                <a href="{{ route('msfl.production.sewing.print', request()->only('date', 'line_id')) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="date" name="date" class="form-control form-control-sm" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}"></div>
                <div class="col-md-3 mb-2">
                    <select name="line_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All lines</option>
                        @foreach($lines as $line)<option value="{{ $line->id }}" @selected(request('line_id') == $line->id)>{{ $line->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.production.sewing.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.production.sewing.partials.board-table', ['board' => $board, 'slots' => $slots, 'breakHour' => $breakHour])
            </div>
            <p class="small text-muted mb-0">
                Hourly cell: output (R = reject, W = rework). Red cell = below the hourly target. DHU = (reject + rework) ÷ checked × 100 ·
                Work Min = manpower × hours × 60 · Prod Min = output × SMV · Efficiency = Prod Min ÷ Work Min.
                Balance = color qty − sewn so far on all lines.
            </p>
        </div>
    </div>
</div>

@push('css')
<style>
    .sew-board { font-size: 11px; white-space: nowrap; }
    .sew-board thead th { background: #6b7280; color: #fff; font-size: 10px; text-transform: uppercase; text-align: center; vertical-align: middle; }
    .sew-board td { vertical-align: middle; }
    .sew-board .sew-plan { background: #fef9e7; }
    .sew-board thead th.sew-plan { background: #6b7280; }
    .sew-board .sew-break { background: #fdecec; color: #dc3545; }
    .sew-board .sew-low strong { color: #dc3545; }
    .sew-board tfoot td { font-weight: 700; background: #eef2ff; }
</style>
@endpush

@can('msfl_prod_entry.add')
    @include('merchandising-sfl::admin.production.sewing.partials.input-modal')
    @include('merchandising-sfl::admin.production.sewing.partials.hourly-modal')
    @push('js')
    <script>
    $(function () {
        const modals = { input: { el: '#sewInputModal', p: 'sewInput' }, hourly: { el: '#sewHourlyModal', p: 'sewHourly' } };

        // Open a modal, optionally for one line / style (row buttons).
        function open(kind, line, po) {
            const m = modals[kind];
            if (line) $('#' + m.p + 'Line').val(String(line)).trigger('change');
            if (po) $('#' + m.p + 'Po').val(String(po)).trigger('change');
            $(m.el).modal('show');
        }
        $(document).on('click', '[data-sew-modal]', function () { open(this.dataset.sewModal, this.dataset.line, this.dataset.po); });

        // The plans and balances on the page are for the board's date: a new date reloads it with the modal open.
        Object.keys(modals).forEach(function (kind) {
            const p = modals[kind].p;
            $('#' + p + 'Date').on('change', function () {
                const url = new URL(window.location.href);
                url.searchParams.set('date', this.value);
                url.searchParams.set('modal', kind);
                ['Line', 'Po'].forEach(f => { const v = $('#' + p + f).val(); v ? url.searchParams.set(f === 'Line' ? 'line' : 'po', v) : url.searchParams.delete(f === 'Line' ? 'line' : 'po'); });
                window.location = url.toString();
            });
        });

        // Reopen after a failed save, or when asked in the URL (?modal=hourly&line=..&po=..).
        const failed = @json(old('_modal'));
        const asked = new URLSearchParams(window.location.search);
        if (failed && modals[failed]) { $(modals[failed].el).modal('show'); }
        else if (modals[asked.get('modal')]) { open(asked.get('modal'), asked.get('line'), asked.get('po')); }
    });
    </script>
    @endpush
@endcan

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
