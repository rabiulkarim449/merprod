{{-- props: result (row_classes: row index => class — subtotal rows, not numbered) --}}
@php $align = $result['align'] ?? []; $statusCol = $result['status_col'] ?? null; $rowClasses = $result['row_classes'] ?? []; $n = 0; @endphp
<table class="table table-bordered table-sm align-middle report-table">
    <thead><tr><th>#</th>@foreach($result['headers'] as $i => $h)<th class="{{ ($align[$i] ?? '') === 'right' ? 'text-right' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
        @forelse($result['rows'] as $r => $row)
            @php $status = $statusCol !== null ? ($row[$statusCol] ?? '') : null; @endphp
            <tr class="{{ $rowClasses[$r] ?? (in_array($status, ['Late', 'Rejected'], true) ? 'table-danger' : (in_array($status, ['At risk'], true) ? 'table-warning' : (in_array($status, ['Packed', 'Approved'], true) ? 'table-success' : ''))) }}">
                <td>{{ isset($rowClasses[$r]) ? '' : ++$n }}</td>
                @foreach($row as $i => $v)
                    <td class="{{ ($align[$i] ?? '') === 'right' ? 'text-right' : '' }}">{{ is_float($v) ? number_format($v, 2) : (is_int($v) && ($align[$i] ?? '') === 'right' ? number_format($v) : $v) }}</td>
                @endforeach
            </tr>
        @empty
            @if($blankRows ?? 0)
                {{-- Printed blank form: empty rows to fill in by hand. --}}
                @for($i = 1; $i <= $blankRows; $i++)<tr style="height:22px"><td>{{ $i }}</td>@foreach($result['headers'] as $h)<td>&nbsp;</td>@endforeach</tr>@endfor
            @else
                <tr><td colspan="{{ count($result['headers']) + 1 }}" class="text-center text-muted">No data for these filters.</td></tr>
            @endif
        @endforelse
    </tbody>
    @if(! empty($result['totals']) && count($result['rows']))
        <tfoot><tr class="font-weight-bold"><td>Total</td>
            @foreach($result['headers'] as $i => $h)
                <td class="text-right">{{ array_key_exists($i, $result['totals']) ? (is_float($result['totals'][$i]) ? number_format($result['totals'][$i], 2) : number_format($result['totals'][$i])) : '' }}</td>
            @endforeach
        </tr></tfoot>
    @endif
</table>
