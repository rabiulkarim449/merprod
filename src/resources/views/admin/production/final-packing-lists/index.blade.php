@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Final Packing Lists' }}@else
    <title>{{ websiteTitle('Final Packing Lists') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Final Packing Lists', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Final Packing Lists</h4>
            @can('msfl_final_packing_list.add')
                <a href="{{ route('msfl.production.final-packing-lists.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Final Packing List"><i class="fa-solid fa-plus"></i> New Final Packing List</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="FPL no / PO no" value="{{ request('search') }}"></div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $b)<option value="{{ $b->id }}" @selected(request('buyer_id') == $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Production\FinalPackingList::statusOptions() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.production.final-packing-lists.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>#</th><th>FPL No</th><th>Date</th><th>Buyer</th><th>Order</th><th>PO No</th><th>Status</th><th>Buyer Approved</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($lists as $fpl)
                            <tr>
                                <td>{{ $loop->iteration + $lists->firstItem() - 1 }}</td>
                                <td><a href="{{ route('msfl.production.final-packing-lists.show', $fpl) }}">{{ $fpl->fpl_no }}</a></td>
                                <td>{{ $fpl->pack_date->format('d M Y') }}</td>
                                <td>{{ $fpl->order->buyer->name ?? '-' }}</td>
                                <td>{{ $fpl->order->order_no ?? '-' }}</td>
                                <td>{{ $fpl->po_no }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $fpl])</td>
                                <td>{{ $fpl->buyer_approved_on?->format('d M Y') ?? '-' }} {{ $fpl->buyer_approved_by ? '· ' . $fpl->buyer_approved_by : '' }}</td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('msfl.production.final-packing-lists.show', $fpl) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @if($fpl->isEditable())
                                        @can('msfl_final_packing_list.edit')<a href="{{ route('msfl.production.final-packing-lists.edit', $fpl) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>@endcan
                                        @can('msfl_final_packing_list.delete')<button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteFplModal" data-action="{{ route('msfl.production.final-packing-lists.destroy', $fpl) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>@endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">No final packing list yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($printMode){{ $lists->links('pagination::bootstrap-5') }}@endunless
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteFplModal', 'label' => 'final packing list'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
