{{-- The bulletin in the factory sheet format. props: bulletin, machineSummary, showInactive (bool) --}}
@php
    $b = $bulletin;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';
    $sl = 0;
@endphp
<table class="ob-sheet ob-head">
    <tr>
        <th>Style No:</th><td class="ob-strong">{{ $b->style->style_no ?? '' }} @if($b->style?->name)({{ $b->style->name }})@endif</td>
        <th>Target/Hr:</th><td class="ob-num">{{ $b->target_per_hour }}</td>
        <th>Tar/{{ rtrim(rtrim((string) $b->working_hours, '0'), '.') }} hrs:</th><td class="ob-num">{{ number_format($b->target_per_day) }}</td>
        <th>Date:</th><td>{{ $b->bulletin_date?->format('d-M-y') }}</td>
    </tr>
    <tr>
        <th>Buyer:</th><td>{{ $b->style->buyer->name ?? '' }}</td>
        <th>SMV:</th><td class="ob-num ob-strong">{{ number_format((float) $b->total_smv, 2) }}</td>
        <th>Helpers:</th><td class="ob-num">{{ $b->helpers }}</td>
        <th>Max:</th><td class="ob-num">{{ $b->max_p_target }}</td>
    </tr>
    <tr>
        <th rowspan="2">Description:</th><td rowspan="2">{{ $b->description }}</td>
        <th>R-SMV:</th><td class="ob-num">{{ number_format((float) $b->r_smv, 2) }}</td>
        <th>Operators:</th><td class="ob-num">{{ $b->operators }}</td>
        <th>Min:</th><td class="ob-num">{{ $b->min_p_target }}</td>
    </tr>
    <tr>
        <th>Utilization:</th><td class="ob-num">{{ round((float) $b->utilization_percent) }}%</td>
        <th>Ttl MP:</th><td class="ob-num">{{ $b->total_manpower }}</td>
        <th>Bottleneck %:</th><td class="ob-num">{{ round((float) $b->bottleneck_percent) }}% <small>· Rev {{ $b->version }}</small></td>
    </tr>
</table>

<table class="ob-sheet ob-ops">
    <thead>
        <tr>
            <th>Sl no</th><th>M/C</th><th>Attachment</th><th>Operation</th><th>SMV</th><th>Tar/Hr</th>
            <th>Req W-Place</th><th>W-Place</th><th>P.Target</th><th>Bottleneck%</th><th>REMARKS</th>
        </tr>
    </thead>
    <tbody>
        @foreach($b->sections() as $section => $ops)
            @php $ops = $showInactive ? $ops : $ops->where('is_active', true); @endphp
            @continue($ops->isEmpty())
            @if($section !== '')
                <tr class="ob-section"><td colspan="11">{{ $section }}</td></tr>
            @endif
            @foreach($ops as $op)
                @php $btl = $b->bottleneckPercent($op); @endphp
                <tr @class(['ob-inactive' => ! $op->is_active, 'ob-bottleneck' => $op->is_active && $btl >= 100])>
                    <td class="ob-c">{{ ++$sl }}</td>
                    <td class="ob-c">{{ $op->machineType->code ?? '' }}</td>
                    <td class="ob-c">{{ $op->attachment }}</td>
                    <td>{{ $op->name }} @unless($op->is_active)<em>(not done)</em>@endunless</td>
                    <td class="ob-num">{{ number_format((float) $op->smv, 3) }}</td>
                    <td class="ob-c">{{ $b->opTargetPerHour($op) }}</td>
                    <td class="ob-num">{{ $op->is_active ? number_format($b->requiredWorkplaces($op), 2) : '' }}</td>
                    <td class="ob-c">{{ $b->workplaces($op) ?: '' }}</td>
                    <td class="ob-c">{{ $b->pTarget($op) ?: '' }}</td>
                    <td class="ob-c">{{ $op->is_active ? $btl . '%' : '' }}</td>
                    <td>{{ $op->remarks }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="4" class="ob-c ob-strong">TOTAL</td><td class="ob-num ob-strong">{{ number_format((float) $b->total_smv, 2) }}</td><td colspan="2"></td><td class="ob-c ob-strong">{{ $b->total_manpower }}</td><td colspan="3"></td></tr>
    </tfoot>
</table>

@php $grid = $machineSummary->filter(fn ($row) => $row['code'] !== '—')->values()->chunk(3); @endphp
<table class="ob-sheet ob-machines">
    <caption>Machine Requirement:</caption>
    @foreach($grid as $chunk)
        <tr>
            @foreach($chunk as $row)
                <td class="ob-c">{{ $row['code'] }}</td><td class="ob-c ob-strong">{{ $row['required'] ?: '' }}</td>
            @endforeach
            @for($i = $chunk->count(); $i < 3; $i++)<td></td><td></td>@endfor
        </tr>
    @endforeach
</table>
