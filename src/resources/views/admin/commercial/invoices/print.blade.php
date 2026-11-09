{{-- Commercial Invoice ($doc = invoice) or Carton Packing List ($doc = packing) on printMaster2. --}}
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
    .ci-lines th, .ci-lines td { font-size: 11px; padding: 4px 6px; }
    .ci-lines .r { text-align: right; } .ci-lines .c { text-align: center; }
    .ci-total td { font-weight: 700; background: #f3f4f6; }
    .ci-words { font-size: 11px; margin: 4px 0 10px; }
    .ci-sign { display: flex; justify-content: space-between; margin-top: 50px; font-size: 11px; }
    .ci-sign div { border-top: 1px solid #333; padding-top: 4px; width: 30%; text-align: center; }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => $doc === 'packing' ? 'Carton Packing List' : 'Commercial Invoice', 'subtitle' => $doc === 'packing' ? $invoice->packingListNo() : $invoice->invoice_no, 'page' => $doc === 'packing' ? 'A4 landscape' : 'A4 portrait'])

    <table class="ci-box">
        <tr>
            <td class="k">{{ $doc === 'packing' ? 'Packing List No / Date' : 'Invoice No / Date' }}</td><td>{{ $doc === 'packing' ? $invoice->packingListNo() : $invoice->invoice_no }} · {{ $invoice->invoice_date->format('d-M-Y') }}@if($doc === 'packing')<br>Invoice No: {{ $invoice->invoice_no }}@endif</td>
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

    @if($doc === 'packing')
        @include('merchandising-sfl::admin.commercial.invoices.partials.carton-list', ['print' => true])
        <p class="ci-words">Total {{ number_format($invoice->total_cartons) }} cartons · {{ number_format($invoice->total_qty) }} pcs · Net weight {{ $invoice->net_weight ?? '-' }} kg · Gross weight {{ $invoice->gross_weight ?? '-' }} kg · {{ $invoice->cbm ?? '-' }} CBM</p>
    @else
        <table class="ci-lines">
            <thead><tr><th>PO No</th><th>Style</th><th>Description</th><th>Color</th><th class="r">Qty (pcs)</th><th class="r">Unit Price ({{ $cur }})</th><th class="r">Amount ({{ $cur }})</th></tr></thead>
            <tbody>
                @foreach($invoice->lines as $line)
                    @php $po = $line->orderPo; @endphp
                    <tr>
                        <td>{{ $po->po_no }}</td><td>{{ $po->style->style_no ?? '' }}</td><td>{{ $po->style->name ?? '' }}</td><td>{{ $po->color->name ?? '' }}</td>
                        <td class="r">{{ number_format($line->qty) }}</td><td class="r">{{ number_format((float) $line->unit_price, 4) }}</td><td class="r">{{ number_format((float) $line->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="ci-total"><td colspan="4" class="r">Total</td><td class="r">{{ number_format($invoice->total_qty) }}</td><td></td><td class="r">{{ $cur }} {{ number_format((float) $invoice->total_value, 2) }}</td></tr>
            </tbody>
        </table>
        <p class="ci-words"><strong>In words:</strong> {{ $invoice->amountInWords() }}</p>
    @endif

    <div class="ci-sign"><div>Prepared by</div><div>Checked by</div><div>Authorised Signature</div></div>
@endsection
