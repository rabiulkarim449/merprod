@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Fabric Requisition' }}@else
    <title>{{ websiteTitle('Fabric Requisition') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Fabric Requisition', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Fabric Requisition</h4>
            <div class="d-flex flex-wrap gap-1">
            @can('msfl_report.view')
                <a href="{{ route('msfl.reports.show', 'requisition-details') }}" class="btn btn-sm ui-act ui-act-view" title="Buyer / Style Details"><i class="fa-solid fa-eye"></i> Buyer / Style Details</a>
                <a href="{{ route('msfl.reports.show', 'requisition-summary') }}" class="btn btn-sm ui-act ui-act-view" title="Summary"><i class="fa-solid fa-eye"></i> Summary</a>
            @endcan
            @if($available)
                @can('msfl_prod_requisition.add')
                    <a href="{{ route('msfl.production.requisitions.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Requisition"><i class="fa-solid fa-plus"></i> New Requisition</a>
                @endcan
            @endif
            </div>
        </div>
        <div class="card-body">
            <p class="small text-muted">Sent to the Inventory store as a normal requisition — the store approves and issues it there; issued quantities show here.</p>
            <form method="GET" class="row mb-3 align-items-end">
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 6])
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.production.requisitions.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Requisition</th><th>Date</th><th>Order / PO</th><th>Style · Color</th><th>Store</th><th>Status</th><th>Items (requested / issued)</th><th>By</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($requisitions as $link)
                            @php $req = $link->requisition; @endphp
                            <tr>
                                <td>@if($req)<a href="{{ route('msfl.production.requisitions.show', $link) }}">{{ $req->requisition_no }}</a>@else(deleted in Inventory)@endif</td>
                                <td>{{ $req?->requisition_date?->format('d-M-Y') ?? '-' }}</td>
                                <td><a href="{{ route('msfl.production.status.show', $link->order_po_id) }}">{{ $link->orderPo->order->order_no ?? '' }} · {{ $link->orderPo->po_no ?? '' }}</a></td>
                                <td>{{ $link->orderPo->style->style_no ?? '' }} · {{ $link->orderPo->color->name ?? '' }}</td>
                                <td>{{ $req->store->name ?? '-' }}</td>
                                <td>@if($req)<span class="badge badge-light border">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>@endif</td>
                                <td class="small">
                                    @foreach($req?->items ?? [] as $item)
                                        <div>{{ $item->item->item_name ?? '-' }}: {{ (float) $item->requested_qty }} / <strong>{{ (float) $item->issued_qty }}</strong></div>
                                    @endforeach
                                </td>
                                <td>{{ $link->creator->name ?? '-' }}</td>
                                <td class="text-right">@if($req)<a href="{{ route('msfl.production.requisitions.show', $link) }}" class="btn-custom ui-act ui-act-view" title="Details"><i class="fa-solid fa-eye"></i></a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">No requisition yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $requisitions->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
