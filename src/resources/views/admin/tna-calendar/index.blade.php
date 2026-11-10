@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('T&A Calendar') }}</title>
@endsection

@push('css')
<style>
    table.tna-cal { min-width: 1500px; margin-bottom: 0; }
    table.tna-cal th { white-space: nowrap; vertical-align: middle; }
    table.tna-cal td { white-space: nowrap; vertical-align: middle; }
    table.tna-cal td.wrap { white-space: normal; min-width: 140px; }
    table.tna-cal tr.plan-first td { border-top: 2px solid #cbd5e1; }
    table.tna-cal td.c { text-align: center; } table.tna-cal td.r { text-align: right; }
    table.tna-cal th.no-entry { color: #94a3b8; }
    table.tna-cal td.no-entry { background: #fafafa; }
    .muted { color: #9ca3af; }
    .tna-summary { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .tna-summary-item { display: flex; align-items: center; gap: 10px; padding: 5px 12px; border: 1px solid #e5e7eb; border-left-width: 4px; border-radius: 6px; background: #fff; white-space: nowrap; }
    .tna-summary-item .k { font-size: 12px; color: #6b7280; }
    .tna-summary-item .v { font-size: 15px; font-weight: 700; color: #111827; }
    .tna-summary-item:nth-child(1) { border-left-color: #3b82f6; }
    .tna-summary-item:nth-child(2) { border-left-color: #22c55e; }
    .tna-summary-item:nth-child(3) { border-left-color: #f59e0b; }
    .tna-summary-item:nth-child(4) { border-left-color: #ef4444; }
    .tna-note { font-size: 11px; color: #6b7280; margin-top: 6px; }
</style>
@endpush

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">T&amp;A Calendar <small class="text-muted">Time &amp; Action — Merchandising Milestone Control</small></h4>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-2 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="T&A / Order / PO / Style" value="{{ request('search') }}"></div>
                <div class="col-md-2 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Services\TnaCalendar::STATUSES as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="Due date from"></div>
                <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="Due date to"></div>
                <div class="col-md-2 mb-2 d-flex align-items-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="closed" value="1" class="custom-control-input" id="closedPlans" @checked(request()->boolean('closed'))>
                        <label class="custom-control-label" for="closedPlans">Include completed T&amp;A</label>
                    </div>
                </div>
                <div class="col-md-12 mb-2 d-flex flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.tna-calendar.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    <a href="{{ route('msfl.tna-calendar.print', request()->query()) }}" target="_blank" class="btn btn-outline-secondary btn-sm btn-icon" title="Print"><i class="fa-solid fa-print"></i></a>
                </div>
            </form>

            @include('merchandising-sfl::admin.tna-calendar.partials.summary')

            <div class="table-responsive">
                @include('merchandising-sfl::admin.tna-calendar.partials.table', ['print' => false])
            </div>
            {{ $plans->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
