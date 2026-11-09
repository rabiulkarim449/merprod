@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Cutting ' . $cutting->cutting_no }}@else
    <title>{{ websiteTitle('Cutting ' . $cutting->cutting_no) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Cutting — ' . $cutting->cutting_no, 'printPage' => 'A4 portrait'])

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Cutting {{ $cutting->cutting_no }}</h4>
            <div class="d-flex gap-1">
                <a href="{{ route('msfl.production.status.show', $cutting->order_po_id) }}" class="btn btn-sm ui-act ui-act-view" title="PO Status"><i class="fa-solid fa-eye"></i> PO Status</a>
                @include('merchandising-sfl::admin.partials.print-button')
                <a href="{{ route('msfl.production.cuttings.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2"><strong>Order / PO:</strong> {{ $cutting->orderPo->order->order_no ?? '' }} · {{ $cutting->orderPo->po_no }}</div>
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $cutting->orderPo->order->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Style:</strong> {{ $cutting->orderPo->style?->label() ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Color:</strong> {{ $cutting->orderPo->color->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Date:</strong> {{ $cutting->cutting_date->format('d-M-Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Table / Lay:</strong> {{ $cutting->table_no ?? '-' }} / {{ $cutting->lay_count ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Fabric Used:</strong> {{ $cutting->fabric_used !== null ? (float) $cutting->fabric_used : '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Total:</strong> {{ number_format($cutting->total_qty) }} pcs · {{ $cutting->bundles->count() }} bundles</div>
                @if($cutting->remarks)<div class="col-12 mb-2"><strong>Remarks:</strong> {{ $cutting->remarks }}</div>@endif
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Sizes</h6></div>
                <table class="table table-bordered table-sm mb-0">
                    <thead><tr><th>Size</th><th class="text-right">Pcs</th></tr></thead>
                    <tbody>@foreach($cutting->sizes as $s)<tr><td>{{ $s->size->name ?? '?' }}</td><td class="text-right">{{ $s->qty }}</td></tr>@endforeach</tbody>
                </table>
            </div>
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Parts Cut <small class="text-muted">— size + color wise</small></h6></div>
                <table class="table table-bordered table-sm mb-0">
                    <thead><tr><th>Part</th><th>Color</th><th>Size</th><th class="text-right">Pcs</th></tr></thead>
                    <tbody>
                        @forelse($cutting->parts->sortBy(fn ($p) => $p->part_name . str_pad((string) ($p->size->sort_order ?? 0), 5, '0', STR_PAD_LEFT)) as $p)
                            <tr><td>{{ $p->part_name }}</td><td>{{ $cutting->orderPo->color->name ?? '-' }}</td><td>{{ $p->size->name ?? 'All' }}</td><td class="text-right">{{ $p->qty }}</td></tr>
                        @empty<tr><td colspan="4" class="text-center text-muted">No parts entered.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0">Bundles <small class="text-muted">({{ $cutting->bundle_size }} pcs each)</small></h6></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead><tr><th>#</th><th>Bundle No</th><th>Size</th><th class="text-right">Pcs</th></tr></thead>
                        <tbody>@foreach($cutting->bundles as $b)<tr><td>{{ $loop->iteration }}</td><td>{{ $b->bundle_no }}</td><td>{{ $b->size->name ?? '?' }}</td><td class="text-right">{{ $b->qty }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
