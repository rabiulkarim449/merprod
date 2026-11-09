@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Orders' }}@else
    <title>{{ websiteTitle('Orders') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Orders', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Orders</h4>
            @can('msfl_order.add')
                <a href="{{ route('msfl.orders.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Order"><i class="fa-solid fa-plus"></i> New Order</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Order / Buyer Ref / Style / PO No" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <input type="text" name="style" class="form-control form-control-sm" placeholder="Style No / Name" value="{{ request('style') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <input type="text" name="po_no" class="form-control form-control-sm" placeholder="PO No" value="{{ request('po_no') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Order::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="is_quick_entry" class="form-control form-control-sm">
                        <option value="">All Orders</option>
                        <option value="1" @selected(request('is_quick_entry') === '1')>Quick Entry only</option>
                        <option value="0" @selected(request('is_quick_entry') === '0')>Full workflow only</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.orders.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Order No</th><th>Date</th><th>Buyer</th><th>Buyer Ref</th><th>Season</th><th>PO Lines</th><th>Total Qty</th><th>Total Value</th><th>Merchandiser</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>{{ $loop->iteration + $orders->firstItem() - 1 }}</td>
                                <td>{{ $order->order_no }} @if($order->is_quick_entry)<span class="badge badge-warning" title="পুরনো/legacy order — Bulletin/T&amp;A/BOM ছাড়া সরাসরি Production-এ ঢোকানো হয়েছে">Quick Entry</span>@endif</td>
                                <td>{{ $order->order_date->format('d M Y') }}</td>
                                <td>{{ $order->buyer->name ?? '-' }}</td>
                                <td>{{ $order->buyer_order_ref ?? '-' }}</td>
                                <td>{{ $order->season->name ?? '-' }}</td>
                                <td>{{ $order->pos_count }}</td>
                                <td>{{ number_format($order->total_qty) }}</td>
                                <td>{{ $order->currency->code ?? '' }} {{ number_format($order->total_value, 2) }}</td>
                                <td>{{ $order->merchandiser->name ?? '-' }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $order])</td>
                                <td class="text-right">
                                    @can('msfl_order.view')
                                        <a href="{{ route('msfl.orders.show', $order) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @if($order->isEditable())
                                        @can('msfl_order.edit')
                                            <a href="{{ route('msfl.orders.edit', $order) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        @endcan
                                    @endif
                                    @if($order->status === 'draft')
                                        @can('msfl_order.delete')
                                            <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteOrderModal" data-action="{{ route('msfl.orders.destroy', $order) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted">No orders found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteOrderModal', 'label' => 'order'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
