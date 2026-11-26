@extends('printMaster2', ['hideGeneratedNote' => true])

@section('title', 'Sample QA Submission Form — ' . $sample->sample_no)

@push('css')
@include('merchandising-sfl::admin.sample-qa-forms.partials.sheet-css')
<style>
    @page { size: A4 landscape; margin: 8mm; }
    .container { max-width: none; }
    .qa-form .qa-page-2 { page-break-before: always; }
    @media print { .qa-form .qa-page + .qa-page { margin-top: 0; } }
    .qa-form tr { page-break-inside: avoid; }
</style>
@endpush

@section('contents')
@include('merchandising-sfl::admin.sample-qa-forms.partials.sheet', ['print' => true])
@endsection
