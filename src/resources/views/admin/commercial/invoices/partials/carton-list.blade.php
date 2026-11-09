{{--
    CARTON PACKING LIST of a commercial invoice (screen and print). One format for every buyer —
    change the layout here only. props: invoice (cartons.sizes, cartons.orderPo.style / color), sizes (columns)
--}}
@php
    $totals = ['ctn' => 0, 'pcs' => 0, 'nw' => 0.0, 'gw' => 0.0, 'cbm' => 0.0];
    $sizeTotals = [];
@endphp
<table class="{{ ($print ?? false) ? 'ci-lines' : 'table table-bordered table-sm align-middle' }}">
    <thead>
        <tr>
            <th>Carton No</th><th class="r">Ctns</th><th>PO No</th><th>Style</th><th>Color</th>
            @foreach($sizes as $s)<th class="c">{{ $s->name }}</th>@endforeach
            <th class="r">Pcs / Ctn</th><th class="r">Total Pcs</th><th class="r">NW / Ctn</th><th class="r">GW / Ctn</th><th class="r">Total NW</th><th class="r">Total GW</th><th>Measurement (cm)</th><th class="r">CBM</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoice->cartons as $c)
            @php
                $n = $c->cartonCount();
                $qty = $c->sizes->pluck('qty', 'size_id');
                $totals['ctn'] += $n; $totals['pcs'] += $c->totalPcs();
                $totals['nw'] += (float) $c->net_weight * $n; $totals['gw'] += (float) $c->gross_weight * $n; $totals['cbm'] += $c->cbm();
                foreach ($qty as $sid => $q) { $sizeTotals[$sid] = ($sizeTotals[$sid] ?? 0) + $q * $n; }
            @endphp
            <tr>
                <td class="text-nowrap">{{ $c->carton_from }}{{ $c->carton_to != $c->carton_from ? ' – ' . $c->carton_to : '' }}</td>
                <td class="r">{{ $n }}</td>
                <td>{{ $c->orderPo->po_no ?? '' }}</td><td>{{ $c->orderPo->style->style_no ?? '' }}</td><td>{{ $c->orderPo->color->name ?? '' }}</td>
                @foreach($sizes as $s)<td class="c">{{ $qty[$s->id] ?? '' }}</td>@endforeach
                <td class="r">{{ $c->pcsPerCarton() }}</td><td class="r">{{ number_format($c->totalPcs()) }}</td>
                <td class="r">{{ $c->net_weight !== null ? number_format((float) $c->net_weight, 2) : '' }}</td>
                <td class="r">{{ $c->gross_weight !== null ? number_format((float) $c->gross_weight, 2) : '' }}</td>
                <td class="r">{{ $c->net_weight !== null ? number_format((float) $c->net_weight * $n, 2) : '' }}</td>
                <td class="r">{{ $c->gross_weight !== null ? number_format((float) $c->gross_weight * $n, 2) : '' }}</td>
                <td class="text-nowrap">{{ $c->length ? (float) $c->length . ' × ' . (float) $c->width . ' × ' . (float) $c->height : '' }}</td>
                <td class="r">{{ $c->cbm() ? number_format($c->cbm(), 3) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 13 + $sizes->count() }}" class="text-center text-muted">No carton rows.</td></tr>
        @endforelse
    </tbody>
    @if($invoice->cartons->isNotEmpty())
        <tfoot>
            <tr class="ci-total font-weight-bold">
                <td>Total</td><td class="r">{{ $totals['ctn'] }}</td><td colspan="3"></td>
                @foreach($sizes as $s)<td class="c">{{ $sizeTotals[$s->id] ?? 0 }}</td>@endforeach
                <td></td><td class="r">{{ number_format($totals['pcs']) }}</td><td></td><td></td>
                <td class="r">{{ number_format($totals['nw'], 2) }}</td><td class="r">{{ number_format($totals['gw'], 2) }}</td><td></td><td class="r">{{ number_format($totals['cbm'], 3) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
