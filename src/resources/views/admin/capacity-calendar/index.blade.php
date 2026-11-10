@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Capacity Calendar') }}</title>
@endsection

@push('css')
<style>
    table.cap-cal { min-width: 1500px; margin-bottom: 0; }
    table.cap-cal th { white-space: nowrap; vertical-align: middle; }
    table.cap-cal td { white-space: nowrap; vertical-align: middle; }
    table.cap-cal td.wrap { white-space: normal; min-width: 140px; }
    table.cap-cal td.c { text-align: center; } table.cap-cal td.r { text-align: right; }
    table.cap-cal th.no-entry { color: #94a3b8; }
    table.cap-cal td.no-entry { background: #fafafa; }
    table.cap-cal tr.off-day td { background: #f3f4f6; color: #9ca3af; }
    table.cap-cal tr.day-first td { border-top: 2px solid #cbd5e1; }
    .muted { color: #9ca3af; }
    .cap-summary { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .cap-summary-item { display: flex; align-items: center; gap: 10px; padding: 5px 12px; border: 1px solid #e5e7eb; border-left-width: 4px; border-radius: 6px; background: #fff; white-space: nowrap; }
    .cap-summary-item .k { font-size: 12px; color: #6b7280; }
    .cap-summary-item .v { font-size: 15px; font-weight: 700; color: #111827; }
    .cap-summary-item:nth-child(1) { border-left-color: #22c55e; }
    .cap-summary-item:nth-child(2) { border-left-color: #3b82f6; }
    .cap-summary-item:nth-child(3) { border-left-color: #f59e0b; }
    .cap-summary-item:nth-child(4) { border-left-color: #8b5cf6; }
    .cap-note { font-size: 11px; color: #6b7280; margin-top: 6px; }
</style>
@endpush

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Capacity Calendar <small class="text-muted">Factory Capacity Per Day — {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</small></h4>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-2 mb-2"><label class="form-label small mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}"></div>
                <div class="col-md-2 mb-2"><label class="form-label small mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="{{ $to->toDateString() }}"></div>
                <div class="col-md-3 mb-2">
                    <select name="line_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Lines</option>
                        @foreach($lines as $l)<option value="{{ $l->id }}" @selected(request('line_id') == $l->id)>{{ $l->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="off" value="1" class="custom-control-input" id="offDays" @checked(request()->boolean('off'))>
                        <label class="custom-control-label" for="offDays">Show off days</label>
                    </div>
                </div>
                <div class="col-md-3 mb-2 d-flex flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.capacity-calendar.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    <a href="{{ route('msfl.capacity-calendar.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary btn-sm btn-icon" title="Print"><i class="fa-solid fa-print"></i></a>
                </div>
            </form>

            <div class="cap-summary">
                @foreach($summary as $label => $value)
                    <div class="cap-summary-item"><span class="k">{{ $label }}</span><span class="v">{{ $value }}</span></div>
                @endforeach
            </div>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.capacity-calendar.partials.table', ['print' => false])
            </div>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
