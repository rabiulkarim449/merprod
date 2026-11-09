@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'BOM' }}@else
    <title>{{ websiteTitle('BOM') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'BOM', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">BOM (Bill of Materials)</h4>
            @can('msfl_bom.add')
                <a href="{{ route('msfl.boms.create') }}" class="btn btn-sm ui-act ui-act-create" title="New BOM"><i class="fa-solid fa-plus"></i> New BOM</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search BOM No / Style No" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="order_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Orders</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}" @selected(request('order_id') == $order->id)>{{ $order->order_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Bom::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.boms.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>BOM No</th><th>Style</th><th>Buyer</th><th>Order</th><th>Version</th><th>Type</th><th>Lines</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($boms as $bom)
                            <tr>
                                <td>{{ $loop->iteration + $boms->firstItem() - 1 }}</td>
                                <td>{{ $bom->bom_no }}</td>
                                <td>{{ $bom->style?->label() ?? '-' }}</td>
                                <td>{{ $bom->style->buyer->name ?? '-' }}</td>
                                <td>{{ $bom->order->order_no ?? '-' }}</td>
                                <td>v{{ $bom->version }}</td>
                                <td>{{ \ME\MerchandisingSfl\Models\Bom::TYPES[$bom->bom_type] ?? $bom->bom_type }}</td>
                                <td>{{ $bom->items_count }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $bom])</td>
                                <td class="text-right">
                                    @can('msfl_bom.view')
                                        <a href="{{ route('msfl.boms.show', $bom) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @if($bom->isEditable())
                                        @can('msfl_bom.edit')
                                            <a href="{{ route('msfl.boms.edit', $bom) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        @endcan
                                        @can('msfl_bom.delete')
                                            <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteBomModal" data-action="{{ route('msfl.boms.destroy', $bom) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">No BOMs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $boms->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteBomModal', 'label' => 'BOM'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
