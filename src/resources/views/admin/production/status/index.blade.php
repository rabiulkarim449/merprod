@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Production Status' }}@else
    <title>{{ websiteTitle('Production Status') }}</title>
@endif
@endsection

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $cols = ['cutting' => 'Cut'] + collect(ProductionFlow::STAGES)->map(fn ($s) => $s[0])->all();
@endphp

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Production Status', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Production Status</h4>
            <small class="text-muted">Each stage: <strong>pass</strong> / in · WIP = in − pass − reject</small>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Order / PO / Style" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.production.status.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle text-nowrap">
                    <thead>
                        <tr>
                            <th>Order / PO</th><th>Buyer</th><th>Style</th><th>Color</th><th class="text-right">PO Qty</th>
                            @foreach($cols as $label)<th class="text-center">{{ $label }}</th>@endforeach
                            <th class="text-right">Reject</th><th class="text-right">Rework</th><th class="text-right">To Pack</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pos as $po)
                            @php $sum = $summaries[$po->id]; @endphp
                            <tr>
                                <td><a href="{{ route('msfl.production.status.show', $po) }}">{{ $po->order->order_no ?? '' }} · {{ $po->po_no }}</a></td>
                                <td>{{ $po->order->buyer->name ?? '-' }}</td>
                                <td>{{ $po->style->style_no ?? '-' }}</td>
                                <td>{{ $po->color->name ?? '-' }}</td>
                                <td class="text-right">{{ number_format($po->po_qty) }}</td>
                                @foreach($cols as $stage => $label)
                                    @if(! isset($sum[$stage]))
                                        <td class="text-center text-muted" title="Not on this PO's route">—</td>
                                    @elseif($stage === 'cutting')
                                        <td class="text-center">{{ number_format($sum[$stage]['pass']) }}</td>
                                    @else
                                        <td class="text-center" title="WIP {{ $sum[$stage]['wip'] }}">
                                            <strong>{{ number_format($sum[$stage]['pass']) }}</strong> <small class="text-muted">/ {{ number_format($sum[$stage]['input']) }}</small>
                                        </td>
                                    @endif
                                @endforeach
                                <td class="text-right {{ collect($sum)->sum('reject') ? 'text-danger' : '' }}">{{ number_format(collect($sum)->sum('reject')) }}</td>
                                <td class="text-right">{{ number_format(collect($sum)->sum('rework')) }}</td>
                                <td class="text-right">{{ number_format(max(0, $po->po_qty - $sum['packing']['pass'])) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 8 + count($cols) }}" class="text-center text-muted">No confirmed order POs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $pos->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
