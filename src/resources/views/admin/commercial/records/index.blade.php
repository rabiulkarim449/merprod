@php
    $printMode = request()->boolean('print');
    $slug = $definition['slug'];
    $perm = $definition['permission'];
@endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

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
                <a href="{{ route("msfl.commercial.$slug.create") }}" class="btn btn-sm ui-act ui-act-create" title="New {{ $definition['singular'] }}"><i class="fa-solid fa-plus"></i> Add</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2"><input type="text" name="search" class="form-control form-control-sm" placeholder="Search" value="{{ request('search') }}"></div>
                @foreach($definition['filters'] ?? [] as $filter => $config)
                    <div class="col-md-3 mb-2">
                        <select name="{{ $filter }}" class="form-control form-control-sm msfl-select2">
                            <option value="">{{ $config['label'] }}</option>
                            @foreach($config['options'] as $value => $label)<option value="{{ $value }}" @selected((string) request($filter) === (string) $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route("msfl.commercial.$slug.index") }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            @foreach($definition['columns'] as $column)<th class="{{ $column['class'] ?? '' }}">{{ $column['label'] }}</th>@endforeach
                            @unless($printMode)<th class="text-right">Actions</th>@endunless
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>{{ $loop->iteration + $records->firstItem() - 1 }}</td>
                                @foreach($definition['columns'] as $column)
                                    @php $value = $column['value'] instanceof \Closure ? ($column['value'])($record) : $record->{$column['value']}; @endphp
                                    <td class="{{ $column['class'] ?? '' }}">@if(! empty($column['html'])){!! $value !!}@else{{ ($value === null || $value === '') ? '-' : $value }}@endif</td>
                                @endforeach
                                @unless($printMode)
                                    <td class="text-right text-nowrap">
                                        @can($perm . '.view')<a href="{{ route("msfl.commercial.$slug.show", $record->id) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>@endcan
                                        @can($perm . '.edit')<a href="{{ route("msfl.commercial.$slug.edit", $record->id) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>@endcan
                                        @can($perm . '.delete')<button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteRecordModal" data-action="{{ route("msfl.commercial.$slug.destroy", $record->id) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>@endcan
                                    </td>
                                @endunless
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($definition['columns']) + 2 }}" class="text-center text-muted">No {{ strtolower($definition['singular']) }} yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @unless($printMode){{ $records->links('pagination::bootstrap-5') }}@endunless
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteRecordModal', 'label' => $definition['singular']])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
