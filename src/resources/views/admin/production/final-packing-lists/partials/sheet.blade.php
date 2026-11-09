{{--
    FINAL PACKING LIST — the sheet (screen and print). One format for every buyer; change the
    layout here only. props: sheet (PackingFigures::finalSheet() or the approved snapshot),
    fpl (FinalPackingList), print (bool)
--}}
@php
    $sizes = $sheet['sizes'];
    $n = count($sizes);
    $grand = array_fill_keys(array_column($sizes, 'id'), 0);
    $running = 0;
@endphp
<style>
    .fpl { width: 100%; font-size: 11px; color: #111; }
    .fpl table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .fpl th, .fpl td { border: 1px solid #333; padding: 2px 5px; font-size: 11px; text-align: center; vertical-align: middle; }
    .fpl th { background: #e5e7eb !important; font-weight: 700; }
    .fpl .l { text-align: left; } .fpl .b { font-weight: 700; }
    .fpl .hk { font-weight: 700; background: #f3f4f6; text-align: left; width: 90px; }
    .fpl .color-cell { writing-mode: vertical-rl; transform: rotate(180deg); font-weight: 700; white-space: nowrap; }
    .fpl .tot td { font-weight: 700; background: #f3f4f6; }
    .fpl .grand td { font-weight: 700; background: #e5e7eb; }
    .fpl .sum-h td, .fpl .sum-h th { background: #d1d5db !important; font-weight: 700; }
    .fpl .short td { background: #f3f4f6; font-weight: 700; }
    .fpl .sign { display: flex; justify-content: space-between; margin-top: 45px; }
    .fpl .sign div { border-top: 1px solid #333; width: 30%; text-align: center; padding-top: 3px; }
    @media print { .fpl th, .fpl .tot td, .fpl .grand td, .fpl .sum-h td, .fpl .short td, .fpl .hk { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
<div class="fpl">
    <table>
        <tr>
            <td class="hk">INVOICE NO</td><td class="l">{{ $sheet['invoices'] ?: ($fpl->invoice_no ?: '—') }}</td>
            <td class="hk">DATE</td><td class="l">{{ $fpl->pack_date?->format('d.m.y') }}</td>
        </tr>
        <tr>
            <td class="hk">BUYER</td><td class="l">{{ $sheet['buyer'] }}</td>
            <td class="hk">ORDER QTY</td><td class="l">{{ $sheet['order_qty'] }}</td>
        </tr>
        <tr>
            <td class="hk">P.O NO</td><td class="l">{{ $sheet['po_no'] }} <small>({{ $sheet['order_no'] }})</small></td>
            <td class="hk">CUTTING QTY</td><td class="l">{{ $sheet['cut_qty'] }}</td>
        </tr>
        <tr>
            <td class="hk">STYLE NO</td><td class="l">{{ $sheet['styles'] }}</td>
            <td class="hk">SHIP QTY</td><td class="l">{{ $sheet['ship_qty'] }}</td>
        </tr>
        <tr>
            <td class="hk">DESCRIPTION</td><td class="l" colspan="3">{{ $sheet['description'] }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr><th rowspan="2" style="width:40px">SL No</th><th rowspan="2" style="width:70px">COLOR</th><th colspan="{{ $n }}">SIZE</th><th rowspan="2">TOTAL QTY</th><th rowspan="2">GRAND TOTAL</th></tr>
            <tr>@foreach($sizes as $s)<th>{{ $s['name'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach($sheet['lines'] as $i => $line)
                @php
                    $running += $line['ship_total'];
                    foreach ($line['ship'] as $sid => $q) { $grand[$sid] = ($grand[$sid] ?? 0) + $q; }
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td rowspan="2"><span class="color-cell">{{ strtoupper($line['color']) }} ({{ $line['style_no'] }})</span></td>
                    @foreach($sizes as $s)<td>{{ $line['ship'][$s['id']] ?: '' }}</td>@endforeach
                    <td>{{ $line['ship_total'] }}</td><td>{{ $running }}</td>
                </tr>
                <tr class="tot"><td>Total</td>@foreach($sizes as $s)<td>{{ $line['ship'][$s['id']] }}</td>@endforeach<td>{{ $line['ship_total'] }}</td><td>{{ $running }}</td></tr>
            @endforeach
            <tr class="grand"><td colspan="2">Grand Total</td>@foreach($sizes as $s)<td>{{ $grand[$s['id']] ?? 0 }}</td>@endforeach<td>{{ array_sum($grand) }}</td><td>{{ array_sum($grand) }}</td></tr>
        </tbody>
    </table>

    @foreach($sheet['lines'] as $line)
        <div class="l b" style="font-size:10px">SUMMARY</div>
        <table>
            <tr class="sum-h"><td class="l">STYLE : {{ $line['style_no'] }}</td><td>COLOR</td>@foreach($sizes as $s)<td>{{ $s['name'] }}</td>@endforeach<td>TOTAL</td><td>REMARKS</td></tr>
            @foreach(['order' => 'Order Qty', 'cut' => 'Cut Qty', 'ship' => 'Ship Qty'] as $key => $label)
                <tr>
                    <td class="l b">{{ $label }}</td>
                    @if($loop->first)<td rowspan="4"><span class="color-cell">{{ strtoupper($line['color']) }}</span></td>@endif
                    @foreach($sizes as $s)<td>{{ $line[$key][$s['id']] ?? 0 }}</td>@endforeach
                    <td class="b">{{ $line[$key . '_total'] }}</td><td></td>
                </tr>
            @endforeach
            <tr class="short">
                <td class="l">Short/Excess Qty</td>
                <td colspan="{{ $n }}">{{ $line['short'] > 0 ? '+' : '' }}{{ $line['short'] }} pcs{{ $line['note'] ? ' — ' . $line['note'] : '' }}</td>
                <td>{{ $line['short'] }}</td><td></td>
            </tr>
        </table>
    @endforeach

    @if($fpl->status === 'approved')
        <div class="l" style="font-size:10px">Buyer approved on {{ $fpl->buyer_approved_on?->format('d-M-Y') }}{{ $fpl->buyer_approved_by ? ' by ' . $fpl->buyer_approved_by : '' }}.</div>
    @endif
    <div class="sign"><div>Quality Manager</div><div>Authorized Signature</div></div>
</div>
