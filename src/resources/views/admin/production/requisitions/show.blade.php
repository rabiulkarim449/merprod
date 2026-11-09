@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Requisition ' . ($req->requisition_no ?? '')) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    @php
        $po = $link->orderPo;
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $issues = ($req?->issues ?? collect())->where('status', '!=', 'cancelled');
    @endphp

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Requisition {{ $req->requisition_no ?? '(deleted in Inventory)' }}
                @if($req)<span class="badge badge-light border">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>@endif</h4>
            <div class="d-flex flex-wrap gap-1">
                @if($req && Route::has('inventory.requisitions.print'))
                    <a href="{{ route('inventory.requisitions.print', $req->id) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
                @endif
                <a href="{{ route('msfl.production.requisitions.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row small">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $po->order->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Style:</strong> {{ $po->style->style_no ?? '-' }} — {{ $po->style->name ?? '' }}</div>
                <div class="col-md-3 mb-2"><strong>Order · PO:</strong> <a href="{{ route('msfl.production.status.show', $po->id) }}">{{ $po->order->order_no ?? '' }} · {{ $po->po_no }}</a></div>
                <div class="col-md-3 mb-2"><strong>Color:</strong> {{ $po->color->name ?? '-' }} ({{ number_format($po->po_qty) }} pcs)</div>
                @if($req)
                    <div class="col-md-3 mb-2"><strong>Requisition Date:</strong> {{ $req->requisition_date?->format('d-M-Y') }}</div>
                    <div class="col-md-3 mb-2"><strong>Store:</strong> {{ $req->store->name ?? '-' }}</div>
                    <div class="col-md-3 mb-2"><strong>Department:</strong> {{ $req->department->name ?? '-' }}</div>
                    <div class="col-md-3 mb-2"><strong>Purpose:</strong> {{ $req->purpose->name ?? $req->requisition_for ?? '-' }}</div>
                    <div class="col-md-3 mb-2"><strong>Raised by:</strong> {{ $link->creator->name ?? $req->requester->name ?? '-' }}</div>
                    <div class="col-md-3 mb-2"><strong>Approved:</strong> {{ $req->approver->name ?? '-' }} {{ $req->approved_at ? '· ' . $req->approved_at->format('d-M-Y H:i') : '' }}</div>
                    @if($req->approval_remarks)<div class="col-md-6 mb-2"><strong>Approval remarks:</strong> {{ $req->approval_remarks }}</div>@endif
                    @if($req->remarks)<div class="col-md-6 mb-2"><strong>Remarks:</strong> {{ $req->remarks }}</div>@endif
                @endif
            </div>
        </div>
    </div>

    @if($req)
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Items — requested / approved / issued</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead><tr><th>Item</th><th>Unit</th><th class="text-right">Requested</th><th class="text-right">Approved</th><th class="text-right">Issued</th><th class="text-right">To Issue</th><th>Issued on (date: qty)</th></tr></thead>
                        <tbody>
                            @foreach($req->items as $item)
                                @php
                                    $lines = $issues->flatMap(fn ($i) => $i->items->where('requisition_item_id', $item->id)->map(fn ($ii) => ['date' => $i->issue_date, 'qty' => $ii->issued_qty]));
                                    $toIssue = max(0, (float) ($item->approved_qty ?? $item->requested_qty) - (float) $item->issued_qty);
                                @endphp
                                <tr>
                                    <td>{{ $item->item->item_code ?? '' }} {{ $item->item->item_name ?? '-' }}</td>
                                    <td>{{ $item->item->unit->short_name ?? '' }}</td>
                                    <td class="text-right">{{ $qty($item->requested_qty) }}</td>
                                    <td class="text-right">{{ $item->approved_qty !== null ? $qty($item->approved_qty) : '-' }}</td>
                                    <td class="text-right"><strong>{{ $qty($item->issued_qty) }}</strong></td>
                                    <td class="text-right {{ $toIssue > 0 ? 'text-danger' : '' }}">{{ $qty($toIssue) }}</td>
                                    <td class="small">{{ $lines->map(fn ($l) => $l['date']?->format('d-M-Y') . ': ' . $qty($l['qty']))->implode(', ') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="text-right">{{ $qty($req->items->sum('requested_qty')) }}</th>
                                <th class="text-right">{{ $qty($req->items->sum('approved_qty')) }}</th>
                                <th class="text-right">{{ $qty($req->items->sum('issued_qty')) }}</th>
                                <th class="text-right">{{ $qty($req->items->sum(fn ($i) => max(0, (float) ($i->approved_qty ?? $i->requested_qty) - (float) $i->issued_qty))) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Issues from the store <small class="text-muted">(when, how much)</small></h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead><tr><th>Issue Date</th><th>Challan</th><th>Status</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Amount</th><th>Issued by</th></tr></thead>
                        <tbody>
                            @forelse($req->issues as $issue)
                                @foreach($issue->items as $ii)
                                    <tr class="{{ $issue->status === 'cancelled' ? 'text-muted' : '' }}">
                                        <td>{{ $issue->issue_date?->format('d-M-Y') }}</td>
                                        <td>{{ $issue->issue_no }}</td>
                                        <td><span class="badge badge-light border">{{ ucfirst(str_replace('_', ' ', $issue->status)) }}</span></td>
                                        <td>{{ $ii->item->item_name ?? '-' }}</td>
                                        <td class="text-right">{{ $qty($ii->issued_qty) }} {{ $ii->item->unit->short_name ?? '' }}</td>
                                        <td class="text-right">{{ number_format((float) $ii->unit_rate, 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $ii->amount, 2) }}</td>
                                        <td>{{ $issue->issuer->name ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">Nothing issued yet — the store issues it in Inventory.</td></tr>
                            @endforelse
                        </tbody>
                        @if($issues->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <th colspan="4">Total issued</th>
                                    <th class="text-right">{{ $qty($issues->sum(fn ($i) => $i->items->sum('issued_qty'))) }}</th>
                                    <th></th>
                                    <th class="text-right">{{ number_format($issues->sum(fn ($i) => $i->items->sum('amount')), 2) }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
