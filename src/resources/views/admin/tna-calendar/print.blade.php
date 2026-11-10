@extends('printMaster2')

@php
    $title = 'Time & Action Calendar' . ($buyer ? ' — ' . $buyer->name : '');
@endphp

@section('title', $title)

@push('css')
<style>
    .container { max-width: none; }
    table.tp { width: 100%; border-collapse: collapse; }
    table.tp th, table.tp td { border: 1px solid #555; padding: 2px 3px; font-size: 9px; line-height: 1.2; color: #000; vertical-align: middle; }
    table.tp thead { display: table-header-group; }
    table.tp thead th { background: #e9ecef; color: #000; font-weight: 700; text-align: center; }
    table.tp td.c { text-align: center; } table.tp td.r { text-align: right; }
    table.tp td.wrap { min-width: 70px; }
    table.tp tr { page-break-inside: avoid; }
    .c-green { background: #d1fae5; } .c-amber { background: #fef3c7; } .c-red { background: #fee2e2; } .c-grey { background: #f3f4f6; color: #6b7280; }
    .muted { color: #6b7280; }
    .tna-summary { display: flex; gap: 6px; margin-bottom: 6px; }
    .tna-summary-item { display: flex; border: 1px solid #555; font-size: 10px; font-weight: 700; }
    .tna-summary-item .k { background: #e9ecef; padding: 3px 8px; }
    .tna-summary-item .v { padding: 3px 12px; text-align: center; }
    .tna-note { font-size: 8px; color: #555; margin-top: 4px; }
    @media print {
        table.tp thead th, .tna-summary-item .k, .tna-summary-item .v, .c-green, .c-amber, .c-red, .c-grey { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => $title, 'subtitle' => 'Merchandising Milestone Control', 'page' => 'A4 landscape'])
    @include('merchandising-sfl::admin.tna-calendar.partials.summary')
    @include('merchandising-sfl::admin.tna-calendar.partials.table', ['print' => true])
@endsection
