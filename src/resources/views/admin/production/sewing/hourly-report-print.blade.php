@extends('printMaster2')

@section('title', 'Daily Hourly Production Report')

@push('css')
<style>
    .container { max-width: none; }
    table.report-table { width: 100%; border-collapse: collapse; }
    table.report-table th, table.report-table td { border: 1px solid #333; padding: 2px 3px; font-size: 9.5px; color: #000; }
    table.report-table thead th { background: #e9ecef; font-size: 8.5px; } .text-right { text-align: right; } .text-center { text-align: center; }
    tfoot td { font-weight: 700; background: #fff59d; }
    @media print { thead th, tfoot td, .sew-break, .hr-hours, .hr-efficiency, .hr-process, .sew-low { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.production.sewing.partials.hourly-style')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => 'Daily Hourly Production Report — Sewing Floor', 'subtitle' => $date->format('d-M-Y'), 'page' => 'A4 landscape'])
    @include('merchandising-sfl::admin.production.sewing.partials.hourly-table', ['print' => true])
@endsection
