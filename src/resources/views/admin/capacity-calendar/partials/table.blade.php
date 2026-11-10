{{--
    Capacity Calendar table — one row per line per day, the client's 17 columns.
    props: rows, print (bool)
--}}
@php $print = $print ?? false; @endphp
<table class="{{ $print ? 'tp' : 'table table-bordered table-sm align-middle cap-cal' }}">
    <thead>
        <tr>
            <th>Date</th><th>Day</th><th class="no-entry" title="Not kept in the system">Shift *</th><th>Working Day</th><th>Line</th><th>Product</th>
            <th>No. of Machines</th><th>Operators</th><th>Helpers</th><th>Regular Hours</th><th>OT Hours</th><th>Available Minutes</th>
            <th>Efficiency %</th><th>SMV</th><th>Gross Capacity (pcs)</th><th>Net Capacity (pcs)</th><th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            @php $num = fn ($v, $d = 0) => $v === null ? '' : number_format($v, $d); @endphp
            <tr class="{{ $r['working'] ? '' : 'off-day' }} {{ ! $loop->first && $rows[$loop->index - 1]['date']->ne($r['date']) ? 'day-first' : '' }}">
                <td class="c">{{ $r['date']->format('d-M-y') }}</td>
                <td class="c">{{ $r['date']->format('D') }}</td>
                <td class="no-entry">{{ $r['shift'] }}</td>
                <td class="c">{{ $r['working'] ? 'Yes' : 'No' }}</td>
                <td>{{ $r['line'] }}</td>
                <td>{{ $r['product'] }}</td>
                <td class="r">{{ $num($r['machines']) }}</td>
                <td class="r">{{ $num($r['operators']) }}</td>
                <td class="r">{{ $num($r['helpers']) }}</td>
                <td class="r">{{ $num($r['regular'], 1) }}</td>
                <td class="r">{{ $r['ot'] ? $num($r['ot'], 1) : '' }}</td>
                <td class="r">{{ $num($r['minutes']) }}</td>
                <td class="r">{{ $num($r['efficiency'], 1) }}%</td>
                <td class="r">{{ $num($r['smv'], 2) }}</td>
                <td class="r">{{ $num($r['gross']) }}</td>
                <td class="r font-weight-bold">{{ $num($r['net']) }}</td>
                <td class="wrap">{{ $r['remarks'] }}@if(! $print && $r['source'])<div class="small text-muted">{{ $r['source'] }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="17" class="c muted">No active line — add lines in Planning → Setup → Lines.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="cap-note">* Shift is not kept in the system yet. Product / SMV / hours come from that day's sewing plan, else the T&amp;A sewing on the line; operators, helpers, regular hours and efficiency from Planning → Setup → Lines.
    Available Minutes = Operators × (Regular + OT Hours) × 60 · Gross = Minutes ÷ SMV · Net = Minutes × Efficiency ÷ SMV.</div>
