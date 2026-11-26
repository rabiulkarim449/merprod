@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Sample Submission Form') }}</title>
@endsection

@php $F = \ME\MerchandisingSfl\Models\SampleQaForm::class; @endphp

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sample Submission Form <small class="text-muted">(Marketing Sample QA)</small></h4>
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-2 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Sample No / Style No" value="{{ request('search') }}">
                </div>
                <div class="col-md-2 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="sample_type_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Types</option>
                        @foreach($sampleTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('sample_type_id') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="form" class="form-control form-control-sm msfl-select2">
                        <option value="">Filled + Not filled</option>
                        <option value="filled" @selected(request('form') === 'filled')>Filled</option>
                        <option value="pending" @selected(request('form') === 'pending')>Not filled</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select name="qa_status" class="form-control form-control-sm msfl-select2">
                        <option value="">All QA Status</option>
                        @foreach($F::QA_STATUS as $k => $l)
                            <option value="{{ $k }}" @selected(request('qa_status') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.sample-qa-forms.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Sample No</th><th>Style</th><th>Buyer</th><th>Sample Type</th><th>Qty</th><th>Required</th><th>QA Inspection</th><th>QA Status</th><th>Form</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($samples as $sample)
                            @php $form = $sample->qaForm; @endphp
                            <tr>
                                <td>{{ $samples->firstItem() + $loop->index }}</td>
                                <td>{{ $sample->sample_no }}</td>
                                <td>{{ $sample->style->style_no ?? '-' }}</td>
                                <td>{{ $sample->buyer->name ?? '-' }}</td>
                                <td>{{ $sample->sampleType->name ?? '-' }}</td>
                                <td>{{ $sample->qty }}</td>
                                <td>{{ optional($sample->required_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ optional($form?->qa_inspection_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ $form && $form->qa_status ? $F::QA_STATUS[$form->qa_status] : '-' }}</td>
                                <td>@if($form)<span class="badge badge-success">Filled</span>@else<span class="badge badge-secondary">Not filled</span>@endif</td>
                                <td class="text-right">
                                    @can('msfl_sample_qa_form.view')
                                        <a href="{{ route('msfl.samples.qa-form.show', $sample) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @can('msfl_sample_qa_form.edit')
                                        <a href="{{ route('msfl.samples.qa-form.edit', $sample) }}" class="btn-custom ui-act ui-act-edit" title="{{ $form ? 'Edit' : 'Fill Form' }}"><i class="fa-solid fa-pen-to-square"></i></a>
                                    @endcan
                                    @can('msfl_sample_qa_form.print')
                                        <a href="{{ route('msfl.samples.qa-form.print', $sample) }}" target="_blank" class="btn-custom ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i></a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">No samples found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $samples->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
