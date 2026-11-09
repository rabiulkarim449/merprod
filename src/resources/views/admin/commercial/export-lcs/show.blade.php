@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Export LC — ' . $lc->lc_no }}@else
    <title>{{ websiteTitle('Export LC ' . $lc->lc_no) }}</title>
@endif
@endsection

@php
    $cur = $lc->currency->code ?? '';
    $money = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
@endphp

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => \ME\MerchandisingSfl\Models\Commercial\ExportLc::TYPES[$lc->type] . ' — ' . $lc->lc_no, 'printSubtitle' => $lc->buyer_lc_no, 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">{{ \ME\MerchandisingSfl\Models\Commercial\ExportLc::TYPES[$lc->type] }} — {{ $lc->lc_no }} @include('merchandising-sfl::admin.partials.status-badge', ['model' => $lc])</h4>
            <div>
                @include('merchandising-sfl::admin.partials.print-button')
                @can('msfl_export_lc.approve')
                    @foreach($lc->status === 'draft' ? ['active' => ['Activate', 'approve', 'fa-check']] : ($lc->status === 'active' ? ['closed' => ['Close LC', 'lock', 'fa-lock']] : ['active' => ['Re-open', 'unlock', 'fa-lock-open']]) as $status => [$label, $act, $icon])
                        <form method="POST" action="{{ route('msfl.commercial.export-lcs.status', $lc) }}" class="d-inline" onsubmit="return confirm('{{ $label }}?');">
                            @csrf <input type="hidden" name="status" value="{{ $status }}">
                            <button type="submit" class="btn btn-sm ui-act ui-act-{{ $act }}" title="{{ $label }}"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</button>
                        </form>
                    @endforeach
                @endcan
                @if($lc->status === 'active')
                    @can('msfl_com_invoice.add')
                        <a href="{{ route('msfl.commercial.invoices.create', ['export_lc_id' => $lc->id]) }}" class="btn btn-sm ui-act ui-act-create" title="New Invoice"><i class="fa-solid fa-plus"></i> New Invoice</a>
                    @endcan
                @endif
                @if($lc->isEditable())
                    @can('msfl_export_lc.edit')
                        <a href="{{ route('msfl.commercial.export-lcs.edit', $lc) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                    @endcan
                @endif
                <a href="{{ route('msfl.commercial.export-lcs.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $lc->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Buyer's {{ strtoupper($lc->type) }} No:</strong> {{ $lc->buyer_lc_no }}</div>
                <div class="col-md-3 mb-2"><strong>Date:</strong> {{ $lc->lc_date->format('d M Y') }}</div>
                <div class="col-md-3 mb-2"><strong>Payment:</strong> {{ $lc->paymentTermLabel() }}</div>
                <div class="col-md-3 mb-2"><strong>Last Shipment:</strong> {{ $lc->last_shipment_date?->format('d M Y') ?? '-' }}
                    @if($figures['last_ship_days'] !== null && $lc->status === 'active')<small class="{{ $figures['last_ship_days'] < 0 ? 'text-danger' : 'text-muted' }}">({{ $figures['last_ship_days'] < 0 ? abs($figures['last_ship_days']) . ' d passed' : $figures['last_ship_days'] . ' d left' }})</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Expiry:</strong> {{ $lc->expiry_date?->format('d M Y') ?? '-' }}
                    @if($figures['expiry_days'] !== null && $lc->status === 'active')<small class="{{ $figures['expiry_days'] < 0 ? 'text-danger' : 'text-muted' }}">({{ $figures['expiry_days'] < 0 ? abs($figures['expiry_days']) . ' d expired' : $figures['expiry_days'] . ' d left' }})</small>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Issuing Bank:</strong> {{ $lc->issuingBank?->label() ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Lien Bank:</strong> {{ $lc->lienBank?->label() ?? '-' }}@if($lc->lienBank?->account_no) <small class="text-muted">A/C {{ $lc->lienBank->account_no }}</small>@endif</div>
                @if($lc->attachment && ! $printMode)
                    @include('merchandising-sfl::admin.commercial.partials.attachments-list', ['value' => $lc->attachment, 'label' => 'Documents'])
                @endif
                @if($lc->remarks)<div class="col-12 mb-2"><strong>Remarks:</strong> {!! nl2br(e($lc->remarks)) !!}</div>@endif
            </div>

            <table class="table table-bordered table-sm mb-3">
                <thead><tr><th class="text-right">LC Value</th><th class="text-right">Tolerance</th><th class="text-right">Limit (value + tolerance)</th><th class="text-right">PO Value</th><th class="text-right">Shipped (invoices)</th><th class="text-right">Balance</th><th class="text-right">Invoices</th></tr></thead>
                <tbody><tr>
                    <td class="text-right">{{ $money($lc->lc_value) }}</td>
                    <td class="text-right">{{ (float) $lc->tolerance_percent }} %</td>
                    <td class="text-right">{{ $money($figures['limit']) }}</td>
                    <td class="text-right {{ abs($figures['po_value'] - (float) $lc->lc_value) > 0.01 ? 'text-danger' : '' }}">{{ $money($figures['po_value']) }}</td>
                    <td class="text-right">{{ $money($figures['shipped']) }}</td>
                    <td class="text-right font-weight-bold {{ $figures['shipped'] > $figures['limit'] ? 'text-danger' : '' }}">{{ $money($figures['balance']) }}</td>
                    <td class="text-right">{{ $figures['invoices'] }}</td>
                </tr></tbody>
            </table>

            <h6>POs on this {{ strtoupper($lc->type) }}</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Order</th><th>PO No</th><th>Style</th><th>Color</th><th class="text-right">Order Qty</th><th class="text-right">FOB</th><th class="text-right">Value</th><th>Shipment</th><th class="text-right">Packed</th><th class="text-right">Invoiced</th><th class="text-right">Can Invoice</th><th class="text-right">Not Shipped</th></tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr>
                                <td>{{ $r['po']->order->order_no ?? '' }}</td>
                                <td>{{ $r['po']->po_no }}</td>
                                <td>{{ $r['po']->style->style_no ?? '-' }}</td>
                                <td>{{ $r['po']->color->name ?? '-' }}</td>
                                <td class="text-right">{{ number_format($r['qty']) }}</td>
                                <td class="text-right">{{ number_format((float) $r['po']->unit_price, 4) }}</td>
                                <td class="text-right">{{ number_format($r['value'], 2) }}</td>
                                <td>{{ $r['po']->shipment_date?->format('d M Y') ?? '-' }}</td>
                                <td class="text-right">{{ number_format($r['packed']) }}</td>
                                <td class="text-right">{{ number_format($r['invoiced']) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($r['available']) }}</td>
                                <td class="text-right">{{ number_format(max(0, $r['qty'] - $r['invoiced'])) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted">No PO on this LC yet — Edit to add.</td></tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                        <tfoot><tr class="font-weight-bold">
                            <td colspan="4" class="text-right">Total</td><td class="text-right">{{ number_format($rows->sum('qty')) }}</td><td></td>
                            <td class="text-right">{{ number_format($rows->sum('value'), 2) }}</td><td></td>
                            <td class="text-right">{{ number_format($rows->sum('packed')) }}</td><td class="text-right">{{ number_format($rows->sum('invoiced')) }}</td>
                            <td class="text-right">{{ number_format($rows->sum('available')) }}</td><td class="text-right">{{ number_format($rows->sum(fn ($r) => max(0, $r['qty'] - $r['invoiced']))) }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>

            <h6>Commercial Invoices</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>Invoice</th><th>Date</th><th>EXP No</th><th>B/L / AWB</th><th>Ship Mode</th><th class="text-right">Qty</th><th class="text-right">Cartons</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                        @forelse($lc->invoices->sortBy('invoice_date') as $inv)
                            <tr>
                                <td><a href="{{ route('msfl.commercial.invoices.show', $inv) }}">{{ $inv->invoice_no }}</a></td>
                                <td>{{ $inv->invoice_date->format('d M Y') }}</td>
                                <td>{{ $inv->exp_no ?? '-' }}</td>
                                <td>{{ $inv->bl_no ?? '-' }}</td>
                                <td>{{ $inv->shipMode->name ?? '-' }}</td>
                                <td class="text-right">{{ number_format($inv->total_qty) }}</td>
                                <td class="text-right">{{ number_format($inv->total_cartons) }}</td>
                                <td class="text-right">{{ number_format((float) $inv->total_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No invoice yet{{ $lc->status === 'active' ? '' : ' — activate the LC first' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($lc->amendments->isNotEmpty())
                <h6>Amendments</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead><tr><th>No</th><th>Date</th><th>Field</th><th>Old</th><th>New</th><th>Note</th><th>By</th></tr></thead>
                        <tbody>
                            @foreach($lc->amendments as $a)
                                <tr>
                                    <td>{{ $a->amendment_no }}</td><td>{{ $a->amendment_date->format('d M Y') }}</td>
                                    <td>{{ \ME\MerchandisingSfl\Models\Commercial\ExportLc::AMENDED[$a->field] ?? $a->field }}</td>
                                    <td>{{ $a->old_value ?? '-' }}</td><td>{{ $a->new_value ?? '-' }}</td><td>{{ $a->remarks }}</td><td>{{ $a->changer->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
