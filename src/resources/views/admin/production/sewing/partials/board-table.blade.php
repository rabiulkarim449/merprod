{{--
    Sewing board table (screen and print). props: board (SewingBoard::rows), slots, breakHour, print (bool)
    One row per line × PO; hourly cells show output (reject / rework below when any).
--}}
@php
    $print = $print ?? false;
    $totals = $board['totals'];
    $n = 0;
    $actions = ! $print && auth()->user()?->can('msfl_prod_entry.add');
    $cols = 16 + count($slots) + 10 + ($actions ? 1 : 0);
@endphp
<table class="{{ $print ? 'report-table' : 'table table-bordered table-sm align-middle sew-board' }}">
    <thead>
        <tr>
            <th>SL</th><th>Line</th><th>Buyer</th><th>Order</th><th class="text-right">Order Qty</th><th>Style</th><th>Color</th>
            <th class="text-right">Color Qty</th><th class="text-right">Line Input</th><th class="text-right">Target</th><th class="text-right">Hour</th>
            <th class="text-right sew-plan">SMV</th><th class="text-right sew-plan">Operator</th><th class="text-right sew-plan">Helper</th><th class="text-right">Manpower</th>
            <th class="text-right">Hourly Target</th>
            @foreach($slots as $h => $slotLabel)
                <th class="text-center {{ $h === $breakHour ? 'sew-break' : '' }}">{{ $slotLabel }}</th>
            @endforeach
            <th class="text-right">Today</th><th class="text-right">Reject</th><th class="text-right">Rework</th><th class="text-right">DHU %</th>
            <th class="text-right">Previous</th><th class="text-right">Grand</th><th class="text-right">Balance</th>
            <th class="text-right">Work Min</th><th class="text-right">Prod Min</th><th class="text-right">Efficiency</th>
            @if($actions)<th class="text-center">Entry</th>@endif
        </tr>
    </thead>
    <tbody>
        @forelse($board['rows'] as $row)
            @if($row['idle'] ?? false)
                <tr class="text-muted">
                    <td>-</td><td>{{ $row['line']->name }}</td>
                    <td colspan="{{ $cols - 2 - ($actions ? 1 : 0) }}" class="text-center small">No Production Running</td>
                    @if($actions)
                        <td class="text-center text-nowrap">
                            <button type="button" class="btn-custom ui-act ui-act-line-input" data-sew-modal="input" data-line="{{ $row['line']->id }}" title="Line Input"><i class="fa-solid fa-arrow-right-to-bracket"></i></button>
                        </td>
                    @endif
                </tr>
                @continue
            @endif
            @php
                $n++;
                $po = $row['po'];
                $perHour = $row['working_hours'] > 0 ? (int) round($row['target'] / $row['working_hours']) : 0;
            @endphp
            <tr>
                <td>{{ $n }}</td>
                <td class="text-nowrap">{{ $row['line']->name }}</td>
                <td>{{ $po->order->buyer->name ?? '-' }}</td>
                <td class="text-nowrap">{{ $po->order->order_no ?? '' }}<br><small class="text-muted">{{ $po->po_no }}</small></td>
                <td class="text-right">{{ number_format($po->order->total_qty ?? 0) }}</td>
                <td>{{ $po->style->style_no ?? '-' }}</td>
                <td>{{ $po->color->name ?? '-' }}</td>
                <td class="text-right">{{ number_format($po->po_qty) }}</td>
                <td class="text-right">{{ number_format($row['input']) }}@if($row['today_input'])<br><small class="text-muted">today {{ number_format($row['today_input']) }}</small>@endif</td>
                <td class="text-right">{{ number_format($row['target']) }}</td>
                <td class="text-right">{{ rtrim(rtrim(number_format($row['working_hours'], 1), '0'), '.') }}</td>
                <td class="text-right sew-plan">{{ rtrim(rtrim(number_format($row['smv'], 3), '0'), '.') }}</td>
                <td class="text-right sew-plan">{{ $row['operators'] }}</td>
                <td class="text-right sew-plan">{{ $row['helpers'] }}</td>
                <td class="text-right"><strong>{{ $row['manpower'] }}</strong></td>
                <td class="text-right">{{ $perHour }}</td>
                @foreach($slots as $h => $slotLabel)
                    @if($h === $breakHour)
                        <td class="text-center sew-break">Break</td>
                        @continue
                    @endif
                    @php $cell = $row['hours'][$h] ?? null; @endphp
                    <td class="text-center {{ $cell && $perHour && $cell['out'] < $perHour ? 'sew-low' : '' }}">
                        @if($cell)
                            <strong>{{ $cell['out'] }}</strong>
                            @if($cell['rej'] || $cell['rw'])<br><small class="text-danger">R{{ $cell['rej'] }} / W{{ $cell['rw'] }}</small>@endif
                        @else
                            <span class="text-muted">–</span>
                        @endif
                    </td>
                @endforeach
                <td class="text-right"><strong>{{ number_format($row['out']) }}</strong></td>
                <td class="text-right text-danger">{{ number_format($row['rej']) }}</td>
                <td class="text-right">{{ number_format($row['rw']) }}</td>
                <td class="text-right">{{ number_format($row['dhu'], 2) }}%</td>
                <td class="text-right">{{ number_format($row['previous']) }}</td>
                <td class="text-right">{{ number_format($row['grand']) }}</td>
                <td class="text-right text-danger">{{ number_format($row['balance']) }}</td>
                <td class="text-right">{{ number_format($row['work_min']) }}</td>
                <td class="text-right">{{ number_format($row['prod_min'], 0) }}</td>
                <td class="text-right"><strong class="{{ $row['efficiency'] >= 60 ? 'text-success' : 'text-danger' }}">{{ number_format($row['efficiency'], 2) }}%</strong></td>
                @if($actions)
                    <td class="text-center text-nowrap">
                        <button type="button" class="btn-custom ui-act ui-act-line-input" data-sew-modal="input" data-line="{{ $row['line']->id }}" data-po="{{ $po->id }}" title="Line Input"><i class="fa-solid fa-arrow-right-to-bracket"></i></button>
                        <button type="button" class="btn-custom ui-act ui-act-hourly-output" data-sew-modal="hourly" data-line="{{ $row['line']->id }}" data-po="{{ $po->id }}" title="Hourly Output"><i class="fa-solid fa-clock"></i></button>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $cols }}" class="text-center text-muted">No sewing line set up — Planning → Setup → Lines.</td></tr>
        @endforelse
    </tbody>
    @if($n)
        <tfoot>
            <tr>
                <td></td><td>Lines: {{ $totals['lines'] }}</td><td>Styles: {{ $totals['styles'] }}</td><td colspan="5"></td>
                <td class="text-right">{{ number_format($totals['input']) }}</td><td class="text-right">{{ number_format($totals['target']) }}</td><td></td><td></td>
                <td class="text-right">{{ $totals['operators'] }}</td><td class="text-right">{{ $totals['helpers'] }}</td><td class="text-right">{{ $totals['manpower'] }}</td><td></td>
                @foreach($slots as $h => $slotLabel)
                    <td class="text-center {{ $h === $breakHour ? 'sew-break' : '' }}">{{ $h === $breakHour ? '' : number_format($totals['hours'][$h] ?? 0) }}</td>
                @endforeach
                <td class="text-right">{{ number_format($totals['out']) }}</td><td class="text-right">{{ number_format($totals['rej']) }}</td><td class="text-right">{{ number_format($totals['rw']) }}</td>
                <td class="text-right">{{ number_format($totals['dhu'], 2) }}%</td>
                <td class="text-right">{{ number_format($totals['previous']) }}</td><td class="text-right">{{ number_format($totals['grand']) }}</td><td></td>
                <td class="text-right">{{ number_format($totals['work_min']) }}</td><td class="text-right">{{ number_format($totals['prod_min'], 0) }}</td>
                <td class="text-right">{{ number_format($totals['efficiency'], 2) }}%</td>
                @if($actions)<td></td>@endif
            </tr>
        </tfoot>
    @endif
</table>
