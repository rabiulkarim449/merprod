@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Cutting' }}@else
    <title>{{ websiteTitle('Cutting') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Cutting', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Cutting</h4>
            @can('msfl_prod_cutting.add')
                <a href="{{ route('msfl.production.cuttings.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Cutting"><i class="fa-solid fa-plus"></i> New Cutting</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cutting No / PO No" value="{{ request('search') }}">
                </div>
                @include('merchandising-sfl::admin.production.partials.po-select', ['filter' => true, 'col' => 6])
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.production.cuttings.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>#</th><th>Cutting No</th><th>Date</th><th>Order / PO</th><th>Buyer</th><th>Style</th><th>Color</th><th>Table</th><th class="text-right">Pcs</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($cuttings as $cutting)
                            <tr>
                                <td>{{ $loop->iteration + $cuttings->firstItem() - 1 }}</td>
                                <td>{{ $cutting->cutting_no }}</td>
                                <td>{{ $cutting->cutting_date->format('d-M-Y') }}</td>
                                <td><a href="{{ route('msfl.production.status.show', $cutting->order_po_id) }}">{{ $cutting->orderPo->order->order_no ?? '' }} · {{ $cutting->orderPo->po_no ?? '' }}</a></td>
                                <td>{{ $cutting->orderPo->order->buyer->name ?? '-' }}</td>
                                <td>{{ $cutting->orderPo->style->style_no ?? '-' }}</td>
                                <td>{{ $cutting->orderPo->color->name ?? '-' }}</td>
                                <td>{{ $cutting->table_no ?? '-' }}</td>
                                <td class="text-right">{{ number_format($cutting->total_qty) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('msfl.production.cuttings.show', $cutting) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @can('msfl_prod_cutting.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteCuttingModal" data-action="{{ route('msfl.production.cuttings.destroy', $cutting) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">No cutting yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $cuttings->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteCuttingModal', 'label' => 'cutting'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
