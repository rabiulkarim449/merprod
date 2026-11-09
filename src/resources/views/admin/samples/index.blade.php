@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Sample Stages' }}@else
    <title>{{ websiteTitle('Sample Stages') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Sample Stages', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Sample Stages</h4>
            @can('msfl_sample.add')
                <a href="{{ route('msfl.samples.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Sample"><i class="fa-solid fa-plus"></i> New Sample</a>
            @endcan
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
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Sample::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-center">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="overdue" value="1" class="custom-control-input" id="overdueFilter" @checked(request()->boolean('overdue'))>
                        <label class="custom-control-label" for="overdueFilter">Overdue only</label>
                    </div>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.samples.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Sample No</th><th>Style</th><th>Buyer</th><th>Type</th><th>Rev</th><th>Qty</th><th>Requested</th><th>Required</th><th>Submitted</th><th>Decision</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($samples as $sample)
                            <tr @class(['table-warning' => $sample->isOverdue()])>
                                <td>{{ $loop->iteration + $samples->firstItem() - 1 }}</td>
                                <td>{{ $sample->sample_no }}</td>
                                <td>{{ $sample->style->style_no ?? '-' }}</td>
                                <td>{{ $sample->buyer->name ?? '-' }}</td>
                                <td>{{ $sample->sampleType->name ?? '-' }}</td>
                                <td>{{ $sample->revision_no }}</td>
                                <td>{{ $sample->qty }}</td>
                                <td>{{ $sample->request_date->format('d M Y') }}</td>
                                <td>{{ optional($sample->required_date)->format('d M Y') ?? '-' }} @if($sample->isOverdue())<span class="badge badge-danger">Overdue</span>@endif</td>
                                <td>{{ optional($sample->submit_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ optional($sample->decision_date)->format('d M Y') ?? '-' }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $sample])</td>
                                <td class="text-right">
                                    @can('msfl_sample.view')
                                        <a href="{{ route('msfl.samples.show', $sample) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @if($sample->isEditable())
                                        @can('msfl_sample.edit')
                                            <a href="{{ route('msfl.samples.edit', $sample) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        @endcan
                                    @endif
                                    @if($sample->status === 'requested')
                                        @can('msfl_sample.delete')
                                            <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteSampleModal" data-action="{{ route('msfl.samples.destroy', $sample) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="text-center text-muted">No samples found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $samples->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteSampleModal', 'label' => 'sample'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
