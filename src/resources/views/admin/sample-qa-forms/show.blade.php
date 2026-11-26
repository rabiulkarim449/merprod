@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Submission Form — ' . $sample->sample_no) }}</title>
@endsection

@push('css')
@include('merchandising-sfl::admin.sample-qa-forms.partials.sheet-css')
@endpush

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-1">
            <h4 class="mb-0">Submission Form — {{ $sample->sample_no }} <small class="text-muted">{{ $sample->sampleType->name ?? '' }}</small></h4>
            <div class="d-flex gap-1 flex-wrap">
                @can('msfl_sample_qa_form.print')
                    <a href="{{ route('msfl.samples.qa-form.print', $sample) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
                @endcan
                @can('msfl_sample_qa_form.edit')
                    <a href="{{ route('msfl.samples.qa-form.edit', $sample) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> {{ $form->exists ? 'Edit' : 'Fill Form' }}</a>
                @endcan
                <a href="{{ route('msfl.sample-qa-forms.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="small text-muted mb-2">
                Customer, Style / Sample ID, PO, Product category, Color / Size, Qty, Sample type, Required date, Costing ref. and Tech pack come from the sample; the rest as filled by QA.
                @if(! $form->exists) <strong>Not filled yet.</strong>@endif
            </div>
            <div class="table-responsive">
                <div style="min-width: 900px;">
                    @include('merchandising-sfl::admin.sample-qa-forms.partials.sheet')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
