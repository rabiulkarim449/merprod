@extends('printMaster2')

@section('title', 'Operation Bulletin — ' . ($bulletin->style->style_no ?? $bulletin->bulletin_no))

@push('css')
    @include('merchandising-sfl::admin.bulletins.partials.sheet-style')
@endpush

@section('contents')
    @include('merchandising-sfl::admin.partials.print-header', ['title' => 'Operation Bulletin', 'subtitle' => $bulletin->bulletin_no . ' · ' . ($bulletin->style->style_no ?? ''), 'page' => 'A4 portrait'])
    @include('merchandising-sfl::admin.bulletins.partials.sheet', ['showInactive' => false])
@endsection
