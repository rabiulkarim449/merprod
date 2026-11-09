@extends('printMaster2')

@section('title', $report->title())

@push('css')
<style>
    @page { size: {{ count($built['columns']) > 18 ? 'A3' : 'A4' }} landscape; margin: 8mm; }
    .container { max-width: none; }
</style>
@endpush

@section('contents')
    @include('merchandising-sfl::admin.ops-reports.partials.sheet', ['editable' => false, 'print' => true])
@endsection
