@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Tech Pack / Styles' }}@else
    <title>{{ websiteTitle('Tech Pack / Styles') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Tech Pack / Styles', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Tech Pack / Styles</h4>
            @can('msfl_style.add')
                <a href="{{ route('msfl.styles.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Tech Pack"><i class="fa-solid fa-plus"></i> New Tech Pack</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Style No / Name" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="buyer_id" class="form-control form-control-sm msfl-select2">
                        <option value="">All Buyers</option>
                        @foreach($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected(request('buyer_id') == $buyer->id)>{{ $buyer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Style::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.styles.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Style No</th><th>Name</th><th>Buyer</th><th>Season</th><th>Product Type</th><th>SMV</th><th>Confirm CM</th><th>Merchandiser</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($styles as $style)
                            <tr>
                                <td>{{ $loop->iteration + $styles->firstItem() - 1 }}</td>
                                <td>{{ $style->style_no }}</td>
                                <td>{{ $style->name }}</td>
                                <td>{{ $style->buyer->name ?? '-' }}</td>
                                <td>{{ $style->season->name ?? '-' }}</td>
                                <td>{{ $style->productType->name ?? '-' }}</td>
                                <td>{{ $style->smv ?? '-' }}</td>
                                <td>{{ $style->confirm_cm ?? '-' }}</td>
                                <td>{{ $style->merchandiser->name ?? '-' }}</td>
                                <td>
                                    @include('merchandising-sfl::admin.partials.status-badge', ['model' => $style])
                                    @unless($style->is_active)<span class="badge badge-secondary">Inactive</span>@endunless
                                </td>
                                <td class="text-right">
                                    @can('msfl_style.view')
                                        <a href="{{ route('msfl.styles.show', $style) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                    @endcan
                                    @can('msfl_style.edit')
                                        <a href="{{ route('msfl.styles.edit', $style) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    @endcan
                                    @can('msfl_style.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteStyleModal" data-action="{{ route('msfl.styles.destroy', $style) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">No styles found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $styles->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteStyleModal', 'label' => 'style'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
