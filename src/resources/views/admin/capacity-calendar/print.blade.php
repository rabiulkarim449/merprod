@extends('printMaster2')

@section('title', 'Factory Capacity Per Day Calendar')

@push('css')
<style>
    .container { max-width: none; }
    table.tp { width: 100%; border-collapse: collapse; }
    table.tp th, table.tp td { border: 1px solid #555; padding: 2px 3px; font-size: 9px; line-height: 1.2; color: #000; vertical-align: middle; }
    table.tp thead { display: table-header-group; }
    table.tp thead th { background: #e9ecef; font-weight: 700; text-align: center; }
    table.tp td.c { text-align: center; } table.tp td.r { text-align: right; }
    table.tp tr { page-break-inside: avoid; }
    table.tp tr.off-day td { background: #f3f4f6; color: #6b7280; }
    .muted { color: #6b7280; }
    .cap-summary { display: flex; gap: 6px; margin-bottom: 6px; }
    .cap-summary-item { display: flex; border: 1px solid #555; font-size: 10px; font-weight: 700; }
    .cap-summary-item .k { background: #e9ecef; padding: 3px 8px; }
    .cap-summary-item .v { padding: 3px 12px; text-align: center; }
    .cap-note { font-size: 8px; color: #555; margin-top: 4px; }
    @media print { table.tp thead th, table.tp tr.off-day td, .cap-summary-item .k { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => 'Factory Capacity Per Day Calendar', 'subtitle' => $from->format('d M Y') . ' – ' . $to->format('d M Y'), 'page' => 'A4 landscape'])
    <div class="cap-summary">
        @foreach($summary as $label => $value)
            <div class="cap-summary-item"><span class="k">{{ $label }}</span><span class="v">{{ $value }}</span></div>
        @endforeach
    </div>
    @include('merchandising-sfl::admin.capacity-calendar.partials.table', ['print' => true])
@endsection
