@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Order ' . $order->order_no }}@else
    <title>{{ websiteTitle('Order ' . $order->order_no) }}</title>
@endif
@endsection

@php
    $canEditPos = $order->isEditable() && auth()->user()->can('msfl_order.edit');
    $statusActions = ['confirmed' => ['Confirm Order', 'approve', 'fa-check', 'msfl_order.approve'], 'closed' => ['Close', 'lock', 'fa-lock', 'msfl_order.edit'], 'cancelled' => ['Cancel Order', 'cancel', 'fa-ban', 'msfl_order.edit']];
@endphp

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Order — ' . $order->order_no, 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Order — {{ $order->order_no }} @if($order->is_quick_entry)<span class="badge badge-warning" title="পুরনো/legacy order — Bulletin/T&amp;A/BOM ছাড়া সরাসরি Production-এ ঢোকানো হয়েছে">Quick Entry</span>@endif</h4>
            <div>
                @foreach($statusActions as $status => [$label, $act, $icon, $permission])
                    @if($order->canMoveTo($status))
                        @can($permission)
                            <form method="POST" action="{{ route('msfl.orders.status', $order) }}" class="d-inline" onsubmit="return confirm('{{ $label }}?');">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button type="submit" class="btn btn-sm ui-act ui-act-{{ $act }}" title="{{ $label }}"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</button>
                            </form>
                        @endcan
                    @endif
                @endforeach
                @if($order->isEditable())
                    @can('msfl_order.edit')
                        <a href="{{ route('msfl.orders.edit', $order) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                    @endcan
                @endif
                @include('merchandising-sfl::admin.partials.print-button')
                <a href="{{ route('msfl.orders.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $order->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Order Date:</strong> {{ $order->order_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Buyer Ref:</strong> {{ $order->buyer_order_ref ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $order])
                    @if($order->confirmer)<small class="text-muted">by {{ $order->confirmer->name }}, {{ $order->confirmed_at->format('d M Y') }}</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Season:</strong> {{ $order->season->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Merchandiser:</strong> {{ $order->merchandiser->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Factory:</strong> {{ $order->factory->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Inquiry:</strong>
                    @if($order->inquiry)<a href="{{ route('msfl.inquiries.show', $order->inquiry) }}">{{ $order->inquiry->inquiry_no }}</a>@else - @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Delivery / Payment:</strong> {{ $order->delivery_term ?? '-' }} / {{ $order->paymentTerm->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Total Qty:</strong> {{ number_format($order->total_qty) }} pcs</div>
                <div class="col-md-3 mb-2"><strong>Total Value:</strong> {{ $order->currency->code ?? '' }} {{ number_format($order->total_value, 2) }}</div>
                <div class="col-md-3 mb-2"><strong>Attachment:</strong>
                    @if($order->attachment)<a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($order->attachment) }}" target="_blank">View</a>@else - @endif
                </div>
                @if($order->remarks)
                    <div class="col-12 mb-2"><strong>Remarks:</strong> {!! nl2br(e($order->remarks)) !!}</div>
                @endif
            </div>

            @include('merchandising-sfl::admin.orders.partials.po-lines')

            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Order BOM</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Style</th><th>Order Qty</th><th>BOM</th><th>Status</th><th>T&amp;A</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($order->pos->groupBy('style_id') as $styleId => $stylePos)
                            @php $styleBoms = $order->boms->where('style_id', $styleId); @endphp
                            <tr>
                                <td>{{ $stylePos->first()->style->label() }}</td>
                                <td>{{ number_format($stylePos->sum('po_qty')) }}</td>
                                <td>
                                    @forelse($styleBoms as $bom)
                                        <a href="{{ route('msfl.boms.show', $bom) }}">{{ $bom->bom_no }}</a> (v{{ $bom->version }})@if(! $loop->last), @endif
                                    @empty
                                        <span class="text-muted">No BOM</span>
                                    @endforelse
                                </td>
                                <td>
                                    @foreach($styleBoms as $bom)
                                        @include('merchandising-sfl::admin.partials.status-badge', ['model' => $bom])
                                    @endforeach
                                </td>
                                <td>
                                    @php $styleTna = $tnaPlans->get($styleId); @endphp
                                    @if($styleTna)
                                        <a href="{{ route('msfl.tna.show', $styleTna) }}">{{ $styleTna->tna_no }}</a>
                                    @elseif($order->status === 'confirmed')
                                        @can('msfl_tna.add')
                                            <a href="{{ route('msfl.tna.create', ['order_id' => $order->id]) }}" class="btn btn-sm btn-icon ui-act ui-act-create" title="Create T&A"><i class="fa-solid fa-plus"></i></a>
                                        @endcan
                                    @else
                                        <span class="text-muted small">after confirmation</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @can('msfl_bom.add')
                                        <a href="{{ route('msfl.boms.create', ['style_id' => $styleId, 'order_id' => $order->id]) }}" class="btn btn-sm btn-icon ui-act ui-act-create" title="Create BOM"><i class="fa-solid fa-plus"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Add PO lines to build the order BOM.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
