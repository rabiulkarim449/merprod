@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Production — ' . $po->po_no }}@else
    <title>{{ websiteTitle('Production — ' . $po->po_no) }}</title>
@endif
@endsection

@php use ME\MerchandisingSfl\Services\ProductionFlow; @endphp

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Production Status — PO ' . $po->po_no, 'printPage' => 'A4 landscape'])

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Production — {{ $po->label() }}</h4>
            @include('merchandising-sfl::admin.partials.print-button')
            <a href="{{ route('msfl.production.status.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $po->order->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Style:</strong> {{ $po->style?->label() ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Color:</strong> {{ $po->color->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>PO Qty:</strong> {{ number_format($po->po_qty) }}
                    @if($po->sizes->isNotEmpty())<small class="text-muted">({{ $po->sizes->map(fn ($s) => ($s->size->name ?? '?') . ' ' . $s->qty)->implode(', ') }})</small>@endif</div>
                <div class="col-md-3 mb-2"><strong>Shipment:</strong> {{ $po->shipment_date?->format('d-M-Y') ?? '-' }}</div>
                <div class="col-md-9 mb-2"><strong>Route:</strong> {{ collect(array_keys($summary))->map(fn ($s) => ProductionFlow::label($s))->implode(' → ') }}
                    <small class="text-muted">(embroidery / washing are set on the order PO)</small></div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead><tr><th>Stage</th><th class="text-right">In</th><th class="text-right">Pass</th><th class="text-right">Rework</th><th class="text-right">Reject</th><th class="text-right">WIP</th><th class="text-right">Can take in</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach($summary as $stage => $row)
                            <tr>
                                <td>{{ ProductionFlow::label($stage) }}</td>
                                <td class="text-right">{{ number_format($row['input']) }}</td>
                                <td class="text-right"><strong>{{ number_format($row['pass']) }}</strong></td>
                                <td class="text-right">{{ number_format($row['rework']) }}</td>
                                <td class="text-right {{ $row['reject'] ? 'text-danger' : '' }}">{{ number_format($row['reject']) }}</td>
                                <td class="text-right">{{ number_format($row['wip']) }}</td>
                                <td class="text-right">{{ $stage === 'cutting' ? '-' : number_format($row['available']) }}</td>
                                <td class="text-right">
                                    @if($stage === 'cutting')
                                        @can('msfl_prod_cutting.add')<a href="{{ route('msfl.production.cuttings.create', ['order_po_id' => $po->id]) }}" class="btn btn-sm btn-icon ui-act ui-act-create" title="Cutting"><i class="fa-solid fa-plus"></i></a>@endcan
                                    @else
                                        @can('msfl_prod_entry.add')<a href="{{ route('msfl.production.entries.create', ['stage' => $stage, 'order_po_id' => $po->id]) }}" class="btn btn-sm btn-icon ui-act ui-act-create" title="Entry"><i class="fa-solid fa-plus"></i></a>@endcan
                                    @endif
                                </td>
                            </tr>
                            @foreach($row['parts'] ?? [] as $part => $p)
                                <tr class="small text-muted">
                                    <td class="pl-4">↳ {{ $part }} <small>(sent from cutting, pass = back to cutting)</small></td>
                                    <td class="text-right">{{ number_format($p['input']) }}</td>
                                    <td class="text-right">{{ number_format($p['pass']) }}</td>
                                    <td class="text-right">{{ number_format($p['rework']) }}</td>
                                    <td class="text-right">{{ number_format($p['reject']) }}</td>
                                    <td class="text-right">{{ number_format($p['wip']) }}</td>
                                    <td class="text-right">{{ number_format($p['available']) }}</td>
                                    <td></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Fabric / Material Requisitions <small class="text-muted">(approved & issued in Inventory)</small></h6>
            @can('msfl_prod_requisition.add')<a href="{{ route('msfl.production.requisitions.create', ['order_po_id' => $po->id]) }}" class="btn btn-sm ui-act ui-act-create" title="Requisition"><i class="fa-solid fa-plus"></i> Requisition</a>@endcan
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead><tr><th>Requisition</th><th>Date</th><th>Store</th><th>Status</th><th>Item</th><th class="text-right">Requested</th><th class="text-right">Approved</th><th class="text-right">Issued</th></tr></thead>
                <tbody>
                    @forelse($requisitions as $link)
                        @php $req = $link->requisition; @endphp
                        @foreach($req?->items ?? [] as $item)
                            <tr>
                                @if($loop->first)
                                    <td rowspan="{{ $req->items->count() }}">{{ $req->requisition_no }}</td>
                                    <td rowspan="{{ $req->items->count() }}">{{ $req->requisition_date?->format('d-M-Y') }}</td>
                                    <td rowspan="{{ $req->items->count() }}">{{ $req->store->name ?? '-' }}</td>
                                    <td rowspan="{{ $req->items->count() }}"><span class="badge badge-light border">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span></td>
                                @endif
                                <td>{{ $item->item->item_name ?? '-' }}</td>
                                <td class="text-right">{{ (float) $item->requested_qty }}</td>
                                <td class="text-right">{{ $item->approved_qty !== null ? (float) $item->approved_qty : '-' }}</td>
                                <td class="text-right">{{ (float) $item->issued_qty }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No requisition for this PO yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Cuttings</h6></div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0">
                <thead><tr><th>Cutting No</th><th>Date</th><th>Sizes</th><th class="text-right">Pcs</th></tr></thead>
                <tbody>
                    @forelse($cuttings as $cutting)
                        <tr>
                            <td><a href="{{ route('msfl.production.cuttings.show', $cutting) }}">{{ $cutting->cutting_no }}</a></td>
                            <td>{{ $cutting->cutting_date->format('d-M-Y') }}</td>
                            <td>{{ $cutting->sizes->map(fn ($s) => ($s->size->name ?? '?') . ' ' . $s->qty)->implode(', ') }}</td>
                            <td class="text-right">{{ number_format($cutting->total_qty) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No cutting yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">Stage Entries</h6></div>
        <div class="table-responsive">
            @include('merchandising-sfl::admin.production.partials.entries-table', ['entries' => $entries, 'showStage' => true, 'showPo' => false])
        </div>
    </div>
</div>
@endsection
