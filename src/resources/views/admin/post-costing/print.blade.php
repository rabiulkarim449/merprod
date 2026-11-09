@extends('printMaster2')

@section('title', 'Post Cost Sheet — ' . $c['po']->po_no)

@push('css')<style> .container { max-width: 1000px; }</style>@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => 'Post Cost Sheet (Budget vs Actual)', 'subtitle' => 'PO ' . $c['po']->po_no, 'page' => 'A4 portrait'])
    @include('merchandising-sfl::admin.post-costing.partials.sheet', ['forPrint' => true])
@endsection
