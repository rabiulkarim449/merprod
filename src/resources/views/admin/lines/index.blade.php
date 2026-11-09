@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Lines' }}@else
    <title>{{ websiteTitle('Lines') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Lines', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Lines</h4>
            @can('msfl_line.add')
                <button type="button" class="btn btn-sm ui-act ui-act-create" data-toggle="modal" data-target="#lineModalnew" title="Add Line"><i class="fa-solid fa-plus"></i> Add Line</button>
            @endcan
        </div>
        <div class="card-body">
            @if($fromInventory && $unmatchedLines->isNotEmpty())
                <div class="alert alert-warning small">
                    Machines in Inventory are on line(s) not set up here, so they aren't counted on any line:
                    @foreach($unmatchedLines as $name => $n)<strong>{{ $name }}</strong> ({{ $n }})@if(! $loop->last), @endif @endforeach.
                    Add a line with that code or name, or correct the machine's Line in Inventory.
                </div>
            @endif
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Floor / line" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.lines.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Floor</th><th>Line</th><th>Operators</th><th>Helpers</th><th>Minutes / Day</th><th>Efficiency</th><th>Available Min (eff.)</th><th>Machines</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr>
                                <td>{{ $loop->iteration + $lines->firstItem() - 1 }}</td>
                                <td>{{ $line->floor ?? '-' }}</td>
                                <td>{{ $line->code ?: '-' }}</td>
                                <td>{{ $line->operators }}</td>
                                <td>{{ $line->helpers }}</td>
                                <td>{{ $line->working_minutes }}</td>
                                <td>{{ (float) $line->efficiency_percent }}%</td>
                                <td title="operators × minutes × efficiency">{{ number_format($line->operators * $line->working_minutes * $line->efficiency_percent / 100) }}</td>
                                <td>
                                    @php $counts = $line->machineCounts(); @endphp
                                    @forelse($counts as $typeId => $n)
                                        <span class="badge badge-light">{{ $machineTypes->firstWhere('id', $typeId)->code ?? '?' }} {{ $n }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                    @if($counts->isNotEmpty())<small class="text-muted">= {{ $counts->sum() }}</small>@endif
                                </td>
                                <td><span class="badge badge-{{ $line->is_active ? 'success' : 'secondary' }}">{{ $line->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-right">
                                    @can('msfl_line.edit')
                                        <button type="button" class="btn-custom ui-act ui-act-edit" data-toggle="modal" data-target="#lineModal{{ $line->id }}" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                    @endcan
                                    @can('msfl_line.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteLineModal" data-action="{{ route('msfl.lines.destroy', $line) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">No lines found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $lines->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@php
    $modals = collect();
    if (auth()->user()->can('msfl_line.add')) { $modals->push(null); }
    if (auth()->user()->can('msfl_line.edit')) { $modals = $modals->merge($lines->items()); }
@endphp
@foreach($modals as $modalLine)
    <div class="modal fade" id="lineModal{{ $modalLine->id ?? 'new' }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ $modalLine ? route('msfl.lines.update', $modalLine) : route('msfl.lines.store') }}">
                    @csrf
                    @if($modalLine) @method('PUT') @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $modalLine ? 'Edit Line — ' . $modalLine->name : 'Add Line' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('merchandising-sfl::admin.lines.partials.fields', ['line' => $modalLine])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> {{ $modalLine ? 'Update' : 'Save' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteLineModal', 'label' => 'line'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
