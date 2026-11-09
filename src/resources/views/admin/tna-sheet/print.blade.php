@extends('printMaster2')

@php
    $title = 'T&A Sheet' . ($buyer ? ' — ' . $buyer->name : '');
    $groups = $sheet->groups();
    $rows = $pos->map(fn ($po) => ['po' => $po, 'cells' => $sheet->row($po)]);
    // 81 columns: four bands, each repeating the Order · PO key.
    $bands = [array_slice($groups, 0, 3, true), array_slice($groups, 3, 2, true), array_slice($groups, 5, 2, true), array_slice($groups, 7, null, true)];
@endphp

@section('title', $title)

@push('css')
<style>
    .container { max-width: none; }
    .band-block { margin-bottom: 10px; }
    table.tp { width: 100%; border-collapse: collapse; }
    table.tp th, table.tp td { border: 1px solid #555; padding: 2px 3px; font-size: 9px; line-height: 1.2; color: #000; text-align: center; vertical-align: middle; }
    table.tp thead { display: table-header-group; }
    table.tp thead th { background: #e9ecef; font-weight: 700; }
    table.tp thead tr.g th { background: #cbd5e1; font-size: 10px; }
    table.tp td.k { text-align: left; font-weight: 700; white-space: nowrap; }
    table.tp td.wrap { text-align: left; min-width: 80px; }
    table.tp tr { page-break-inside: avoid; }
    .c-green { background: #d1fae5; } .c-amber { background: #fef3c7; } .c-red { background: #fee2e2; } .c-grey { background: #f3f4f6; color: #6b7280; } .c-blue { background: #eef4ff; }
    .tna-hint { color: #6b7280; font-style: italic; }
    .legend { font-size: 9px; margin-bottom: 6px; } .legend .tna-legend-item { margin-right: 10px; }
    .tna-swatch { display: inline-block; width: 10px; height: 10px; border: 1px solid #999; vertical-align: middle; }
    @media print { .c-green, .c-amber, .c-red, .c-grey, .c-blue, table.tp thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => $title, 'subtitle' => null, 'page' => 'A3 landscape'])
    <div class="legend">@include('merchandising-sfl::admin.tna-sheet.partials.legend')</div>

    @foreach($bands as $band)
        <div class="band-block">
            <table class="tp">
                <thead>
                    <tr class="g"><th rowspan="2">#</th><th rowspan="2">Order · PO</th>@foreach($band as $name => $cols)<th colspan="{{ count($cols) }}">{{ $name }}</th>@endforeach</tr>
                    <tr>@foreach($band as $cols)@foreach($cols as [$label])<th>{{ $label }}</th>@endforeach @endforeach</tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="k">{{ $r['po']->order->order_no }}<br>{{ $r['po']->po_no }}</td>
                            @foreach($band as $cols)
                                @foreach($cols as $key => $col)
                                    @php $c = $r['cells'][$key]; @endphp
                                    <td class="c-{{ $c['color'] }} @if(str_contains($key, 'remarks') || $key === 'pcd_reason' || $key === 'style') wrap @endif">@if($c['text'] !== ''){{ $c['text'] }}@elseif($c['hint'])<em class="tna-hint">({{ $c['hint'] }})</em>@endif</td>
                                @endforeach
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ 2 + array_sum(array_map('count', $band)) }}">No confirmed order PO.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
@endsection
