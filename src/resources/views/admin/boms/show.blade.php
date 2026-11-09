@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'BOM ' . $bom->bom_no }}@else
    <title>{{ websiteTitle('BOM ' . $bom->bom_no) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'BOM — ' . $bom->bom_no, 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">BOM — {{ $bom->bom_no }} <small class="text-muted">v{{ $bom->version }}</small></h4>
            <div>
                @include('merchandising-sfl::admin.partials.print-button')
                @if($bom->isEditable())
                    @can('msfl_bom.edit')
                        <a href="{{ route('msfl.boms.edit', $bom) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                    @endcan
                    @can('msfl_bom.approve')
                        <form method="POST" action="{{ route('msfl.boms.approve', $bom) }}" class="d-inline" onsubmit="return confirm('Approve this BOM? It cannot be edited afterwards.');">
                            @csrf
                            <button type="submit" class="btn btn-sm ui-act ui-act-approve" title="Approve"><i class="fa-solid fa-check"></i> Approve</button>
                        </form>
                    @endcan
                @else
                    @can('msfl_bom.add')
                        <form method="POST" action="{{ route('msfl.boms.revise', $bom) }}" class="d-inline" onsubmit="return confirm('Create a new draft version from this BOM?');">
                            @csrf
                            <button type="submit" class="btn btn-sm ui-act ui-act-revise" title="Revise"><i class="fa-solid fa-code-branch"></i> Revise</button>
                        </form>
                    @endcan
                @endif
                <a href="{{ route('msfl.boms.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Style:</strong> <a href="{{ route('msfl.styles.show', $bom->style) }}">{{ $bom->style->label() }}</a></div>
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $bom->style->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Order:</strong>
                    @if($bom->order)<a href="{{ route('msfl.orders.show', $bom->order) }}">{{ $bom->order->order_no }}</a> — {{ number_format($bom->order->styleQty($bom->style_id)) }} pcs @else <span class="text-muted">None (per piece BOM)</span> @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $bom])
                    @if($bom->approver)<small class="text-muted">by {{ $bom->approver->name }}, {{ $bom->approved_at->format('d M Y') }}</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Type:</strong> {{ \ME\MerchandisingSfl\Models\Bom::TYPES[$bom->bom_type] ?? $bom->bom_type }}</div>
                @if($bom->bom_file)
                    <div class="col-md-3 mb-2"><strong>Buyer File:</strong> <a href="{{ $bom->fileUrl() }}" target="_blank"><i class="fa-solid fa-file"></i> View</a></div>
                @endif
                @if($bom->remarks)
                    <div class="col-12 mb-2"><strong>Remarks:</strong> {!! nl2br(e($bom->remarks)) !!}</div>
                @endif
            </div>

            @if($bom->items->isNotEmpty())
                @php $grandAmount = 0; @endphp
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                            <tr>
                                <th>#</th><th>Item</th><th>Type</th><th>Color</th><th>Size</th><th>Placement</th>
                                <th class="text-right">Cons / pc</th><th>Unit</th><th class="text-right">Wastage %</th><th class="text-right">Gross / pc</th>
                                @if($bom->order)<th class="text-right">Order Qty</th><th class="text-right">Required Qty</th>@endif
                                <th class="text-right">Rate</th>@if($bom->order)<th class="text-right">Amount</th>@endif
                                <th>Supplier</th><th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bom->items as $line)
                                @php
                                    $orderQty = $bom->orderQtyFor($line);
                                    $required = $line->requiredQty($orderQty);
                                    $amount = $required * (float) $line->rate;
                                    $grandAmount += $amount;
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $line->item->name ?? '-' }} <small class="text-muted">{{ $line->item->code ?? '' }}</small></td>
                                    <td>{{ ucfirst($line->item_type) }}</td>
                                    <td>{{ $line->color->name ?? 'All' }}</td>
                                    <td>{{ $line->size->name ?? 'All' }}</td>
                                    <td>{{ $line->placement ?? '-' }}</td>
                                    <td class="text-right">{{ rtrim(rtrim(number_format($line->consumption, 4), '0'), '.') }}</td>
                                    <td>{{ $line->uom->code ?? '-' }}</td>
                                    <td class="text-right">{{ (float) $line->wastage_percent }}</td>
                                    <td class="text-right">{{ number_format($line->grossConsumption(), 4) }}</td>
                                    @if($bom->order)
                                        <td class="text-right">{{ number_format($orderQty) }}</td>
                                        <td class="text-right font-weight-bold">{{ number_format($required, 2) }}</td>
                                    @endif
                                    <td class="text-right">{{ $line->rate !== null ? number_format($line->rate, 4) : '-' }}</td>
                                    @if($bom->order)<td class="text-right">{{ number_format($amount, 2) }}</td>@endif
                                    <td>{{ $line->supplier->name ?? '-' }}</td>
                                    <td>{{ $line->remarks }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if($bom->order)
                            <tfoot><tr><th colspan="13" class="text-right">Total Material Value</th><th class="text-right">{{ number_format($grandAmount, 2) }}</th><th colspan="2"></th></tr></tfoot>
                        @endif
                    </table>
                </div>
            @elseif($bom->bom_type === 'manual')
                <p class="text-muted">No item lines.</p>
            @endif
        </div>
    </div>
</div>
@endsection
