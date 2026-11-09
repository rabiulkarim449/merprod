@extends('printMaster2')

@section('title', $title)

@push('css')
<style>
    .container { max-width: none; }
    table.report-table { width: 100%; border-collapse: collapse; }
    table.report-table th, table.report-table td { border: 1px solid #555; padding: 3px 5px; font-size: 10.5px; color: #000; }
    table.report-table thead th { background: #e9ecef; } .text-right { text-align: right; } .text-center { text-align: center; }
    .table-danger td { background: #fee2e2; } .table-warning td { background: #fef3c7; } .table-success td { background: #dcfce7; }
    tfoot td { font-weight: 700; background: #f3f4f6; }
    .table-secondary td { background: #e5e7eb; font-weight: 700; } .font-weight-bold td { font-weight: 700; }
    .report-section-title { font-size: 12px; font-weight: 700; margin: 8px 0 3px; }
    @media print { thead th, .table-danger td, .table-warning td, .table-success td, tfoot td { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .table-secondary td { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => $title, 'subtitle' => $result['period'] ?? null, 'page' => 'A4 landscape'])
    @include('merchandising-sfl::admin.reports.partials.result', ['result' => $result, 'blankRows' => 5])
@endsection
