{{-- props: c (PostCosting::build), forPrint (bool) --}}
@php
    $b = $c['budget']; $a = $c['actual']; $q = $c['qty']; $cur = $c['currency'];
    $m = fn ($v) => $v === null ? '-' : number_format($v, 2);
    $var = fn ($budget, $actual, $higherIsBad = true) => $budget === null ? '' : (($d = $actual - $budget) == 0 ? '0.00' : (($d > 0) === $higherIsBad ? '<span class="pc-bad">' : '<span class="pc-good">') . ($d > 0 ? '+' : '') . number_format($d, 2) . '</span>');
@endphp
<style>
    table.pc { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.pc th, table.pc td { border: 1px solid #bbb; padding: 4px 6px; font-size: 12.5px; color: #000; }
    table.pc thead th { background: #e9ecef; text-align: center; }
    table.pc td.n { text-align: right; } table.pc tr.tot td { font-weight: 700; background: #f8f9fa; }
    .pc-bad { color: #b91c1c; font-weight: 600; } .pc-good { color: #15803d; font-weight: 600; }
    .pc-note { font-size: 11.5px; color: #555; }
</style>

<table class="pc">
    <tbody>
        <tr><th style="width:16%">Buyer</th><td>{{ $c['po']->order->buyer->name ?? '-' }}</td><th style="width:16%">Order / PO</th><td>{{ $c['po']->order->order_no }} · {{ $c['po']->po_no }}</td></tr>
        <tr><th>Style</th><td>{{ $c['po']->style?->label() }}</td><th>Color</th><td>{{ $c['po']->color->name ?? '-' }}</td></tr>
        <tr><th>Cost Sheet</th><td>{{ $c['costSheet']->cost_sheet_no ?? 'No approved cost sheet' }}</td><th>FOB / pc</th><td>{{ $cur }} {{ number_format($c['po']->unit_price, 4) }}</td></tr>
    </tbody>
</table>

<table class="pc">
    <thead><tr><th>Quantity (pcs)</th><th>Order</th><th>Cut</th><th>Packed</th><th>Rejected (all stages)</th><th>Cut → Packed</th><th>Order → Packed</th></tr></thead>
    <tbody><tr><td>Pieces</td><td class="n">{{ number_format($q['order']) }}</td><td class="n">{{ number_format($q['cut']) }}</td><td class="n">{{ number_format($q['packed']) }}</td>
        <td class="n">{{ number_format($q['rejects']) }}</td><td class="n">{{ $q['cut_to_pack'] === null ? '-' : $q['cut_to_pack'] . '%' }}</td><td class="n">{{ $q['order_to_pack'] === null ? '-' : $q['order_to_pack'] . '%' }}</td></tr></tbody>
</table>

<table class="pc">
    <thead><tr><th style="width:34%">Cost head ({{ $cur }})</th><th>Budget (order qty)</th><th>Actual</th><th>Variance</th><th>Actual from</th></tr></thead>
    <tbody>
        <tr><td>Material (fabric + trims)</td><td class="n">{{ $m($b ? $b['fabric'] + $b['trims'] : null) }}</td><td class="n">{{ $m($a['material']) }}</td><td class="n">{!! $var($b ? $b['fabric'] + $b['trims'] : null, $a['material']) !!}</td><td class="pc-note">{!! $a['has_rates'] ? 'Inventory issues against this PO' : '<strong>Estimate</strong> — budget material / pc × packed (no store value issued against this PO yet)' !!}</td></tr>
        <tr><td>Process (wash / print / emb)</td><td class="n">{{ $m($b['process'] ?? null) }}</td><td class="n">{{ $m($a['process']) }}</td><td class="n">{!! $var($b['process'] ?? null, $a['process']) !!}</td><td class="pc-note">Budget per dozen × packed (no actual source yet)</td></tr>
        <tr><td>Commercial</td><td class="n">{{ $m($b['commercial'] ?? null) }}</td><td class="n">{{ $m($a['commercial']) }}</td><td class="n">{!! $var($b['commercial'] ?? null, $a['commercial']) !!}</td><td class="pc-note">{{ $c['costSheet'] ? (float) $c['costSheet']->commercial_percent . '% of actual material' : '' }}</td></tr>
        <tr><td>Other</td><td class="n">{{ $m($b['other'] ?? null) }}</td><td class="n">{{ $m($a['other']) }}</td><td class="n">{!! $var($b['other'] ?? null, $a['other']) !!}</td><td class="pc-note">Budget per dozen × packed</td></tr>
        <tr class="tot"><td>Revenue (FOB)</td><td class="n">{{ $m($b['revenue'] ?? null) }}</td><td class="n">{{ $m($a['revenue']) }}</td><td class="n">{!! $var($b['revenue'] ?? null, $a['revenue'], false) !!}</td><td class="pc-note">Packed pcs × FOB</td></tr>
        <tr class="tot"><td>CM</td><td class="n">{{ $m($b['cm'] ?? null) }}</td><td class="n">{{ $m($a['cm_earned']) }}</td><td class="n">{!! $var($b['cm'] ?? null, $a['cm_earned'], false) !!}</td><td class="pc-note">FOB − material − commercial − process − other @if($b)<br>(cost sheet CM line: {{ $m($b['cm_costsheet']) }})@endif</td></tr>
        <tr><td>Reject loss (material in rejected pcs)</td><td class="n">-</td><td class="n">{{ $m($a['reject_loss']) }}</td><td></td><td class="pc-note">{{ number_format($q['rejects']) }} pcs × budget material / pc</td></tr>
    </tbody>
</table>

<table class="pc">
    <thead><tr><th>Issued from Inventory (item)</th><th style="width:15%">Qty</th><th style="width:15%">Value (BDT)</th></tr></thead>
    <tbody>
        @forelse($c['issued'] as $i)
            <tr><td>{{ $i->item_name }}</td><td class="n">{{ rtrim(rtrim(number_format($i->qty, 2), '0'), '.') }} {{ $i->unit }}</td><td class="n">{{ number_format($i->amount, 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="pc-note" style="text-align:center">Nothing issued from the store against this PO yet (raise it from Production → Fabric Requisition).</td></tr>
        @endforelse
    </tbody>
</table>
@unless($c['costSheet'])<p class="pc-note">No approved cost sheet for this style — budget columns are empty.</p>@endunless
<p class="pc-note">Amounts in the cost sheet's currency; Inventory values (BDT) are converted with the currency's exchange rate (Master Data → Currencies).</p>
