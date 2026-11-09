@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@php $label = \ME\MerchandisingSfl\Services\ProductionFlow::label($stage); @endphp

@section('title')
@if($printMode){{ $label }}@else
    <title>{{ websiteTitle($label) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => $label, 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $label }}</h4>
            @can('msfl_prod_entry.add')
                @if($stage === 'sewing')
                    {{-- Sewing is entered line by line on the Sewing board. --}}
                    <a href="{{ route('msfl.production.sewing.input') }}" class="btn btn-sm ui-act ui-act-line-input" title="Line Input"><i class="fa-solid fa-arrow-right-to-bracket"></i> Line Input</a>
                    <a href="{{ route('msfl.production.sewing.hourly') }}" class="btn btn-sm ui-act ui-act-hourly-output" title="Hourly Output"><i class="fa-solid fa-clock"></i> Hourly Output</a>
                    <a href="{{ route('msfl.production.sewing.index') }}" class="btn btn-sm ui-act ui-act-nav" title="Board"><i class="fa-solid fa-table"></i> Board</a>
                @elseif(in_array($stage, \ME\MerchandisingSfl\Services\ProductionFlow::SPLIT_STAGES, true))
                    {{-- Sent and received back on their own dates. --}}
                    @php [$sendLabel, $receiveLabel] = \ME\MerchandisingSfl\Services\ProductionFlow::splitLabels($stage); @endphp
                    <a href="{{ route('msfl.production.entries.create', ['stage' => $stage, 'mode' => 'input']) }}" class="btn btn-sm ui-act ui-act-create" title="Add"><i class="fa-solid fa-plus"></i> {{ $sendLabel }}</a>
                    <a href="{{ route('msfl.production.entries.create', ['stage' => $stage, 'mode' => 'output']) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> {{ $receiveLabel }}</a>
                @else
                    <a href="{{ route('msfl.production.entries.create', ['stage' => $stage]) }}" class="btn btn-sm ui-act ui-act-create" title="Add"><i class="fa-solid fa-plus"></i> New {{ $label }} Entry</a>
                @endif
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 4])
                <div class="col-md-2 mb-2"><input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" title="From"></div>
                <div class="col-md-2 mb-2"><input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" title="To"></div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ $stage === 'sewing' ? route('msfl.production.sewing.entries') : route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                @include('merchandising-sfl::admin.production.partials.entries-table', ['entries' => $entries, 'showStage' => false, 'showPo' => true])
            </div>
            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
