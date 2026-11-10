{{--
    Commercial Invoice ($doc = invoice) — the client's "Commercial Sales Invoice" layout — or the
    Carton Packing List ($doc = packing), on printMaster2.
--}}
@extends('printMaster2')

@section('title', $doc === 'packing' ? 'Carton Packing List — ' . $invoice->packingListNo() : 'Commercial Invoice — ' . $invoice->invoice_no)

@php
    $lc = $invoice->exportLc;
    $cur = $lc->currency->code ?? '';
    $buyer = $invoice->buyer;
@endphp

@push('css')
<style>
    .ci-box td { vertical-align: top; font-size: 11px; padding: 4px 6px; }
    .ci-box .k { font-weight: 700; white-space: nowrap; background: #f3f4f6; width: 120px; }
    .ci-words { font-size: 11px; margin: 4px 0 10px; }
    .ci-sign { display: flex; justify-content: space-between; margin-top: 50px; font-size: 11px; }
    .ci-sign div { border-top: 1px solid #333; padding-top: 4px; width: 30%; text-align: center; }

    /* Sales invoice (client layout), standard print table colours: grey label cells, white values. */
    .si-head, .si-lines, .si-totals { width: 100%; border-collapse: collapse; }
    .si-head td, .si-lines th, .si-lines td, .si-totals td { border: 1px solid #555; font-size: 10px; padding: 3px 5px; color: #000; vertical-align: top; }
    .si-head td.k, .si-lines th, .si-totals td.k { background: #e9ecef; color: #000; font-weight: 700; }
    .si-head td.k { width: 11%; white-space: nowrap; }
    .si-head td.v { width: 14%; }
    .si-lines th { text-align: center; vertical-align: middle; }
    .si-lines .r, .si-totals .r { text-align: right; white-space: nowrap; } .si-lines .c { text-align: center; }
    .si-lines tfoot td { font-weight: 700; background: #f3f4f6; }
    .si-sub { font-size: 10px; font-style: italic; margin: 0 0 4px; }
    /* Words + bank box on the left, totals on the right; kept on one page with the signatures. */
    .si-end { page-break-inside: avoid; break-inside: avoid; }
    .si-bottom { display: flex; justify-content: space-between; align-items: stretch; gap: 12px; margin-top: 6px; }
    .si-left { flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .si-words { font-size: 10px; }
    .si-totals { width: 280px; }
    .si-totals td { padding: 2px 5px; }
    .si-totals td.k { width: 140px; }
    .si-totals tr.grand td { font-weight: 700; }
    .si-bank { border: 1px solid #555; padding: 4px 7px; font-size: 10px; flex: 1; min-height: 40px; }
    .si-bank .t { font-weight: 700; margin-bottom: 2px; }
    .si-end .ci-sign { margin-top: 32px; }
    @media print { .si-head td.k, .si-lines th, .si-totals td.k, .si-lines tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    @if($doc === 'packing')
        @include('merchandising-sfl::admin.partials.print-header', ['title' => 'Carton Packing List', 'subtitle' => $invoice->packingListNo(), 'page' => 'A4 landscape'])

        <table class="ci-box">
            <tr>
                <td class="k">Packing List No / Date</td><td>{{ $invoice->packingListNo() }} · {{ $invoice->invoice_date->format('d-M-Y') }}<br>Invoice No: {{ $invoice->invoice_no }}</td>
                <td class="k">{{ strtoupper($lc->type) }} No / Date</td><td>{{ $lc->buyer_lc_no }} · {{ $lc->lc_date->format('d-M-Y') }}</td>
            </tr>
            <tr>
                <td class="k">Buyer / Consignee</td><td>{{ $buyer->name ?? '' }}@if($buyer?->address)<br>{!! nl2br(e($buyer->address)) !!}@endif @if($buyer?->country)<br>{{ $buyer->country }}@endif</td>
                <td class="k">Issuing Bank</td><td>{{ $lc->issuingBank?->label() ?? '-' }}@if($lc->issuingBank?->address)<br>{{ $lc->issuingBank->address }}@endif
                    <br><span class="k" style="background:none;padding:0">Our Bank:</span> {{ $lc->lienBank?->label() ?? '-' }}@if($lc->lienBank?->account_no) · A/C {{ $lc->lienBank->account_no }}@endif</td>
            </tr>
            <tr>
                <td class="k">EXP No / Date</td><td>{{ $invoice->exp_no ?? '-' }} {{ $invoice->exp_date ? '· ' . $invoice->exp_date->format('d-M-Y') : '' }}</td>
                <td class="k">B/L / AWB No / Date</td><td>{{ $invoice->bl_no ?? '-' }} {{ $invoice->bl_date ? '· ' . $invoice->bl_date->format('d-M-Y') : '' }}</td>
            </tr>
            <tr>
                <td class="k">Ship Mode / Vessel</td><td>{{ $invoice->shipMode->name ?? '-' }} {{ $invoice->vessel ? '· ' . $invoice->vessel : '' }}</td>
                <td class="k">Container</td><td>{{ $invoice->container_no ?? '-' }}</td>
            </tr>
            <tr>
                <td class="k">Port of Loading</td><td>{{ $invoice->port_of_loading ?? '-' }}</td>
                <td class="k">Port of Discharge / Final Dest.</td><td>{{ $invoice->port_of_discharge ?? '-' }} {{ $invoice->final_destination ? '/ ' . $invoice->final_destination : '' }}</td>
            </tr>
            <tr>
                <td class="k">Payment Terms</td><td>{{ $lc->paymentTermLabel() }}</td>
                <td class="k">Weight / CBM</td><td>Net {{ $invoice->net_weight ?? '-' }} kg · Gross {{ $invoice->gross_weight ?? '-' }} kg · CBM {{ $invoice->cbm ?? '-' }}</td>
            </tr>
        </table>

        @include('merchandising-sfl::admin.commercial.invoices.partials.carton-list', ['print' => true])
        <p class="ci-words">Total {{ number_format($invoice->total_cartons) }} cartons · {{ number_format($invoice->total_qty) }} pcs · Net weight {{ $invoice->net_weight ?? '-' }} kg · Gross weight {{ $invoice->gross_weight ?? '-' }} kg · {{ $invoice->cbm ?? '-' }} CBM</p>
        <div class="ci-sign"><div>Prepared by</div><div>Checked by</div><div>Authorised Signature</div></div>
    @else
        @php
            $d = fn ($date) => $date?->format('d-M-Y') ?? '';
            $bank = $lc->lienBank;
            $incoterm = $invoice->lines->map(fn ($l) => $l->orderPo->order->delivery_term ?? null)->filter()->unique()->implode(' / ');
            $sizeNames = $sizes->pluck('name', 'id');
            // Per PO from the carton list: pcs per size, net / gross weight.
            $perPo = $invoice->cartons->groupBy('order_po_id')->map(function ($cartons) {
                $sizeQty = [];
                foreach ($cartons as $c) {
                    foreach ($c->sizes as $s) {
                        $sizeQty[$s->size_id] = ($sizeQty[$s->size_id] ?? 0) + $s->qty * $c->cartonCount();
                    }
                }

                return [
                    'sizes' => $sizeQty,
                    'net' => $cartons->sum(fn ($c) => (float) $c->net_weight * $c->cartonCount()),
                    'gross' => $cartons->sum(fn ($c) => (float) $c->gross_weight * $c->cartonCount()),
                ];
            });
            $money = fn ($v) => number_format((float) $v, 2);
        @endphp

        @include('merchandising-sfl::admin.partials.print-header', ['title' => 'Commercial Sales Invoice', 'subtitle' => $invoice->invoice_no, 'page' => 'A4 landscape'])
        <p class="si-sub">Commercial Sales Invoice | Export / Local Sales</p>

        <table class="si-head">
            <tr>
                <td class="k">Invoice No.</td><td class="v">{{ $invoice->invoice_no }}</td>
                <td class="k">Invoice Date</td><td class="v">{{ $d($invoice->invoice_date) }}</td>
                <td class="k">Currency</td><td class="v">{{ $cur }}</td>
                <td class="k">Exchange Rate</td><td class="v">{{ $invoice->exchange_rate !== null ? rtrim(rtrim(number_format((float) $invoice->exchange_rate, 4), '0'), '.') : '' }}</td>
            </tr>
            <tr>
                <td class="k">Buyer / Bill To</td><td class="v">{{ $buyer->name ?? '' }}</td>
                <td class="k">Consignee</td><td class="v">{{ $invoice->consignee_name ?: ($buyer->name ?? '') }}</td>
                <td class="k">Contract / LC No.</td><td class="v">{{ $lc->buyer_lc_no }}</td>
                <td class="k">Payment Terms</td><td class="v">{{ $lc->paymentTermLabel() }}</td>
            </tr>
            <tr>
                <td class="k">Buyer Address</td><td class="v">{!! nl2br(e($buyer->address ?? '')) !!}@if($buyer?->country)<br>{{ $buyer->country }}@endif</td>
                <td class="k">Consignee Address</td><td class="v">@if($invoice->consignee_address){!! nl2br(e($invoice->consignee_address)) !!}@else{!! nl2br(e($buyer->address ?? '')) !!}@if($buyer?->country)<br>{{ $buyer->country }}@endif @endif</td>
                <td class="k">Country of Origin</td><td class="v">{{ $invoice->country_of_origin }}</td>
                <td class="k">Destination Country</td><td class="v">{{ $invoice->final_destination }}</td>
            </tr>
            <tr>
                <td class="k">Port of Loading</td><td class="v">{{ $invoice->port_of_loading }}</td>
                <td class="k">Port of Discharge</td><td class="v">{{ $invoice->port_of_discharge }}</td>
                <td class="k">Incoterm</td><td class="v">{{ $incoterm }}</td>
                <td class="k">Shipment Mode</td><td class="v">{{ $invoice->shipMode->name ?? '' }}</td>
            </tr>
            <tr>
                <td class="k">Vessel / Flight</td><td class="v">{{ $invoice->vessel }}</td>
                <td class="k">Container / AWB</td><td class="v">{{ collect([$invoice->container_no, $invoice->bl_no])->filter()->implode(' · ') }}</td>
                <td class="k">EXP No.</td><td class="v">{{ $invoice->exp_no }}{{ $invoice->exp_date ? ' · ' . $d($invoice->exp_date) : '' }}</td>
                <td class="k">Shipment Date</td><td class="v">{{ $d($invoice->shipment_date ?? $invoice->bl_date) }}</td>
            </tr>
        </table>

        <table class="si-lines" style="margin-top:8px">
            <thead>
                <tr>
                    <th>Sl.</th><th>Style</th><th>PO</th><th>HS Code</th><th>Product Description</th><th>Color / Size</th><th>Cartons</th>
                    <th>Quantity</th><th>Unit</th><th>Unit Price ({{ $cur }})</th><th>Line Amount ({{ $cur }})</th><th>Net Weight (Kg)</th><th>Gross Weight (Kg)</th><th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $line)
                    @php
                        $po = $line->orderPo;
                        $p = $perPo[$line->order_po_id] ?? ['sizes' => [], 'net' => 0, 'gross' => 0];
                        // Size break-up; a PO packed as one "All size" needs none.
                        $sizeQty = collect($p['sizes'])->sortKeysUsing(fn ($a, $b) => $sizeNames->keys()->search($a) <=> $sizeNames->keys()->search($b));
                        $sizeText = $sizeQty->count() === 1 && str_contains(strtolower((string) ($sizeNames[$sizeQty->keys()->first()] ?? '')), 'all') ? ''
                            : $sizeQty->map(fn ($q, $id) => ($sizeNames[$id] ?? '?') . '-' . $q)->implode(', ');
                    @endphp
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $po->style->style_no ?? '' }}</td>
                        <td>{{ $po->po_no }}</td>
                        <td>{{ $po->style->hs_code ?? '' }}</td>
                        <td>{{ $po->style->name ?? '' }}</td>
                        <td>{{ $po->color->name ?? '' }}@if($sizeText)<br><small>{{ $sizeText }}</small>@endif</td>
                        <td class="r">{{ number_format($line->cartons) }}</td>
                        <td class="r">{{ number_format($line->qty) }}</td>
                        <td class="c">Pcs</td>
                        <td class="r">{{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="r">{{ $money($line->amount) }}</td>
                        <td class="r">{{ $p['net'] ? $money($p['net']) : '' }}</td>
                        <td class="r">{{ $p['gross'] ? $money($p['gross']) : '' }}</td>
                        <td>{{ $line->remarks }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="r">Total</td>
                    <td class="r">{{ number_format($invoice->total_cartons) }}</td>
                    <td class="r">{{ number_format($invoice->total_qty) }}</td>
                    <td></td><td></td>
                    <td class="r">{{ $money($invoice->total_value) }}</td>
                    <td class="r">{{ $invoice->net_weight !== null ? $money($invoice->net_weight) : '' }}</td>
                    <td class="r">{{ $invoice->gross_weight !== null ? $money($invoice->gross_weight) : '' }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="si-end">
        <div class="si-bottom">
            <div class="si-left">
                <div class="si-words"><strong>In words:</strong> {{ $invoice->amountInWords() }}
                    @if($invoice->remarks)<br><strong>Remarks:</strong> {{ $invoice->remarks }}@endif
                </div>
                <div class="si-bank">
                    <div class="t">Bank Details / Special Declaration:</div>
                    @if($bank)
                        <div>{{ $bank->name }}{{ $bank->branch ? ', ' . $bank->branch : '' }}@if($bank->account_no) · A/C {{ $bank->account_no }}@endif @if($bank->swift_code) · SWIFT {{ $bank->swift_code }}@endif @if($bank->ad_code) · AD Code {{ $bank->ad_code }}@endif</div>
                        @if($bank->address)<div>{{ $bank->address }}</div>@endif
                    @endif
                    @if($invoice->special_declaration)<div style="margin-top:3px">{!! nl2br(e($invoice->special_declaration)) !!}</div>@endif
                </div>
            </div>
            <table class="si-totals">
                <tr><td class="k">Subtotal</td><td class="r">{{ $cur }} {{ $money($invoice->total_value) }}</td></tr>
                <tr><td class="k">Discount</td><td class="r">{{ $money($invoice->discount) }}</td></tr>
                <tr><td class="k">Freight / Other</td><td class="r">{{ $money($invoice->freight_other) }}</td></tr>
                <tr class="grand"><td class="k">Invoice Total</td><td class="r">{{ $cur }} {{ $money($invoice->invoiceTotal()) }}</td></tr>
                <tr><td class="k">Advance Received</td><td class="r">{{ $money($invoice->advance_received) }}</td></tr>
                <tr class="grand"><td class="k">Balance Due</td><td class="r">{{ $cur }} {{ $money($invoice->balanceDue()) }}</td></tr>
            </table>
        </div>

        <div class="ci-sign"><div>Prepared By</div><div>Checked By</div><div>Authorized Signature</div></div>
        </div>
    @endif
@endsection
