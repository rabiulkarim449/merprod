{{--
    Daily Hourly Production Report table (screen and print). props: board (SewingBoard::rows), slots, breakHour, print (bool)
    Three rows per line × PO: Output, Efficiency %, DHU % for every hour; grand total the same over all lines.
--}}
@php
    $print = $print ?? false;
    $totals = $board['totals'];
    $rows = collect($board['rows'])->reject(fn ($r) => $r['idle'] ?? false);
    $pct = fn ($v) => $v ? rtrim(rtrim(number_format($v, 1), '0'), '.') . '%' : '';
    $num = fn ($v, $d = 3) => rtrim(rtrim(number_format((float) $v, $d), '0'), '.');
    $processes = ['Output', 'Efficiency', 'DHU'];
@endphp
<table class="{{ $print ? 'report-table' : 'table table-bordered table-sm align-middle' }} hr-table">
    <thead>
        <tr>
            <th rowspan="2">Line</th><th rowspan="2">Buyer</th><th rowspan="2">Style</th><th rowspan="2" class="text-right">Order Qty</th>
            <th rowspan="2" class="text-right">Man Power</th><th rowspan="2" class="text-right">SMV</th><th rowspan="2">Input Start Date</th>
            <th rowspan="2" class="text-right">Running Day</th><th rowspan="2" class="text-right">Target Eff %</th><th rowspan="2" class="text-right">Hourly Target</th>
            <th rowspan="2">Process</th>
            <th colspan="{{ count($slots) }}" class="text-center hr-hours">Hours</th>
            <th rowspan="2" class="text-right">Total</th><th rowspan="2">Remarks</th>
        </tr>
        <tr>
            @foreach($slots as $h => $slotLabel)
                <th class="text-center {{ $h === $breakHour ? 'sew-break' : 'hr-hours' }}">{{ $slotLabel }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            @php
                $po = $row['po'];
                $perHour = $row['working_hours'] > 0 ? (int) round($row['target'] / $row['working_hours']) : 0;
            @endphp
            @foreach($processes as $p)
                <tr class="{{ $loop->last ? 'hr-last' : '' }}">
                    @if($loop->first)
                        <td rowspan="3" class="hr-line">{{ $row['line']->code }}</td>
                        <td rowspan="3">{{ $po->order->buyer->name ?? '-' }}</td>
                        <td rowspan="3">{{ $po->style->style_no ?? '-' }}@if($po->color)<br><small>{{ $po->color->name }}</small>@endif</td>
                        <td rowspan="3" class="text-right">{{ number_format($po->po_qty) }}</td>
                        <td rowspan="3" class="text-right hr-big">{{ $row['manpower'] }}</td>
                        <td rowspan="3" class="text-right">{{ $num($row['smv']) }}</td>
                        <td rowspan="3" class="text-nowrap">{{ $row['input_start']?->format('d-M-y') ?? '-' }}</td>
                        <td rowspan="3" class="text-right hr-big">{{ $row['running_day'] ?: '-' }}</td>
                        <td rowspan="3" class="text-right">{{ $pct($row['target_eff']) ?: '-' }}</td>
                        <td rowspan="3" class="text-right hr-big">{{ $perHour ?: '-' }}</td>
                    @endif
                    <td class="hr-process hr-{{ strtolower($p) }}">{{ $p }}</td>
                    @foreach($slots as $h => $slotLabel)
                        @if($h === $breakHour)
                            <td class="sew-break"></td>
                            @continue
                        @endif
                        @php $cell = $row['hours'][$h] ?? null; @endphp
                        @if($p === 'Output')
                            <td class="text-center {{ $cell && $perHour && $cell['out'] < $perHour ? 'sew-low' : '' }}">{{ $cell ? $cell['out'] : '' }}</td>
                        @elseif($p === 'Efficiency')
                            <td class="text-center hr-efficiency">{{ $cell ? $pct($row['hour_eff'][$h] ?? 0) ?: '0%' : '' }}</td>
                        @else
                            <td class="text-center">{{ $cell ? $pct($row['hour_dhu'][$h] ?? 0) ?: '0%' : '' }}</td>
                        @endif
                    @endforeach
                    <td class="text-right"><strong>{{ $p === 'Output' ? number_format($row['out']) : ($p === 'Efficiency' ? ($pct($row['efficiency']) ?: '0%') : ($pct($row['dhu']) ?: '0%')) }}</strong></td>
                    @if($loop->first)
                        <td rowspan="3">{{ $row['remarks'] }}</td>
                    @endif
                </tr>
            @endforeach
        @empty
            <tr><td colspan="{{ 13 + count($slots) }}" class="text-center text-muted">No sewing production on this day.</td></tr>
        @endforelse
    </tbody>
    @if($rows->isNotEmpty())
        <tfoot>
            @foreach($processes as $p)
                <tr>
                    @if($loop->first)
                        <td rowspan="3" colspan="4" class="hr-big">Grand Total Output</td>
                        <td rowspan="3" class="text-right hr-big">{{ $totals['manpower'] }}</td>
                        <td rowspan="3" class="text-right">{{ $num($totals['smv'], 2) }}</td>
                        <td rowspan="3"></td><td rowspan="3"></td>
                        <td rowspan="3" class="text-right">{{ $pct($totals['target_eff']) ?: '-' }}</td>
                        <td rowspan="3" class="text-right hr-big">{{ number_format($totals['hourly_target']) }}</td>
                    @endif
                    <td class="hr-process hr-{{ strtolower($p) }}">{{ $p }}</td>
                    @foreach($slots as $h => $slotLabel)
                        @if($h === $breakHour)
                            <td class="sew-break"></td>
                            @continue
                        @endif
                        @php $any = $rows->contains(fn ($r) => isset($r['hours'][$h])); @endphp
                        <td class="text-center">{{ ! $any ? '' : ($p === 'Output' ? number_format($totals['hours'][$h]) : ($pct($p === 'Efficiency' ? $totals['hour_eff'][$h] : $totals['hour_dhu'][$h]) ?: '0%')) }}</td>
                    @endforeach
                    <td class="text-right">{{ $p === 'Output' ? number_format($totals['out']) : ($pct($p === 'Efficiency' ? $totals['efficiency'] : $totals['dhu']) ?: '0%') }}</td>
                    @if($loop->first)<td rowspan="3"></td>@endif
                </tr>
            @endforeach
        </tfoot>
    @endif
</table>
