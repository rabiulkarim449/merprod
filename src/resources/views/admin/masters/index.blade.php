@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@php
    $perm = $definition['permission'];
    $slug = $definition['slug'];
    $modalKey = \Illuminate\Support\Str::studly($slug);
@endphp

@section('title')
@if($printMode){{ $definition['title'] }}@else
    <title>{{ websiteTitle($definition['title']) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => $definition['title'], 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $definition['title'] }}</h4>
            @can($perm . '.add')
                <button type="button" class="btn btn-sm ui-act ui-act-create" data-toggle="modal" data-target="#create{{ $modalKey }}Modal" title="Add {{ $definition['singular'] }}"><i class="fa-solid fa-plus"></i> Add</button>
            @endcan
        </div>
        <div class="card-body">
            @if(! empty($definition['note']))
                <div class="alert alert-info small">{!! is_callable($definition['note']) ? ($definition['note'])() : e($definition['note']) !!}</div>
            @endif
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search" value="{{ request('search') }}">
                </div>
                @foreach($definition['filters'] ?? [] as $filter => $filterOptions)
                    <div class="col-md-3 mb-2">
                        <select name="{{ $filter }}" class="form-control form-control-sm msfl-select2">
                            <option value="">All {{ ucfirst(trim(str_replace(['_id', '_'], ['', ' '], $filter))) }}</option>
                            @foreach($filterOptions as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @selected(request($filter) === (string) $optionValue)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.masters.index', $slug) }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            @foreach($definition['columns'] as $column)
                                <th>{{ $column['label'] }}</th>
                            @endforeach
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>{{ $loop->iteration + $records->firstItem() - 1 }}</td>
                                @foreach($definition['columns'] as $column)
                                    <td>{{ \ME\MerchandisingSfl\Support\MasterRegistry::columnValue($column, $record) }}</td>
                                @endforeach
                                <td>
                                    <span class="badge badge-{{ $record->is_active ? 'success' : 'secondary' }}">
                                        {{ $record->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    @can($perm . '.edit')
                                        <button type="button" class="btn-custom ui-act ui-act-edit" data-toggle="modal" data-target="#edit{{ $modalKey }}Modal{{ $record->id }}" title="Edit"><i class="fa-solid fa-pen-to-square"></i></button>
                                    @endcan
                                    @can($perm . '.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#delete{{ $modalKey }}Modal" data-action="{{ route('msfl.masters.destroy', [$slug, $record->id]) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>

                            @can($perm . '.edit')
                                <div class="modal fade" id="edit{{ $modalKey }}Modal{{ $record->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('msfl.masters.update', [$slug, $record->id]) }}">
                                                @csrf @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit {{ $definition['singular'] }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                </div>
                                                <div class="modal-body">
                                                    @include('merchandising-sfl::admin.masters.partials.fields', ['record' => $record])
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                                                    <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Update"><i class="fa-solid fa-floppy-disk"></i> Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                        @empty
                            <tr><td colspan="{{ count($definition['columns']) + 3 }}" class="text-center text-muted">No {{ strtolower($definition['title']) }} found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $records->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@can($perm . '.add')
    <div class="modal fade" id="create{{ $modalKey }}Modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('msfl.masters.store', $slug) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add {{ $definition['singular'] }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('merchandising-sfl::admin.masters.partials.fields', ['record' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'delete' . $modalKey . 'Modal', 'label' => strtolower($definition['singular'])])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
