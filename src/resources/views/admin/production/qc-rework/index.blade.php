@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $title = $kind === 'qc' ? 'QC' : 'Rework';
    $icons = ['cutting' => 'fa-solid fa-scissors'] + collect(ProductionFlow::STAGES)->map(fn ($s) => $s[1])->all();
@endphp

@section('title')
@if($printMode){{ $title }}@else
    <title>{{ websiteTitle($title) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => $title, 'printPage' => 'A4 landscape'])
    <style>
        .msfl-step-card { display: block; border: 1px solid #dee2e6; border-radius: 6px; padding: 14px; color: inherit; height: 100%; transition: box-shadow .15s, border-color .15s; }
        .msfl-step-card:hover { border-color: #007bff; box-shadow: 0 2px 8px rgba(0, 123, 255, .15); text-decoration: none; color: inherit; }
        .msfl-step-card i { font-size: 1.4rem; color: #007bff; }
    </style>

    <div class="card mb-3">
        <div class="card-header">
            <h4 class="mb-0">{{ $title }} <small class="text-muted">{{ $kind === 'qc' ? '— step-wise QC (Buyer QC has its own menu)' : '— rework pieces fixed' }}</small></h4>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-3">Click a step to enter its {{ strtolower($title) }}.</p>
            <div class="row">
                @foreach($stages as $key => $label)
                    @php $t = $today->get($key); @endphp
                    <div class="col-lg-2 col-md-3 col-6 mb-3">
                        @can('msfl_prod_entry.add')
                            <a href="{{ route('msfl.production.' . $kind . '.create', ['stage' => $key]) }}" class="msfl-step-card">
                        @else
                            <div class="msfl-step-card">
                        @endcan
                            <i class="{{ $icons[$key] ?? 'fa-solid fa-circle' }}"></i>
                            <div class="font-weight-bold mt-2">{{ $label }} {{ $title }}</div>
                            @if(ProductionFlow::partWise($key))<div class="small text-info">part-wise</div>@endif
                            <div class="small text-muted mt-1">Today:
                                @if($kind === 'qc')
                                    reject <strong class="text-danger">{{ (int) ($t->rj ?? 0) }}</strong> · rework <strong>{{ (int) ($t->rw ?? 0) }}</strong>
                                @else
                                    passed <strong class="text-success">{{ (int) ($t->p ?? 0) }}</strong> · reject <strong class="text-danger">{{ (int) ($t->rj ?? 0) }}</strong>
                                @endif
                            </div>
                        @can('msfl_prod_entry.add')
                            </a>
                        @else
                            </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ $title }} entries</h6></div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-2 mb-2">
                    <select name="stage" class="form-control form-control-sm">
                        <option value="">All steps</option>
                        @foreach($stages as $key => $label)<option value="{{ $key }}" @selected(request('stage') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 4])
                <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="From"></div>
                <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="To"></div>
                <div class="col-md-2 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.production.' . $kind . '.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.production.partials.entries-table', ['entries' => $entries, 'showStage' => true, 'showPo' => true])
            </div>
            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
