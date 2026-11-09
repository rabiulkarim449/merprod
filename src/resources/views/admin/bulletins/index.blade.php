@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Bulletin' }}@else
    <title>{{ websiteTitle('Bulletin') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Bulletin', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Bulletin (Operation Breakdown)</h4>
            @can('msfl_bulletin.add')
                <a href="{{ route('msfl.bulletins.create') }}" class="btn btn-sm ui-act ui-act-create" title="New Bulletin"><i class="fa-solid fa-plus"></i> New Bulletin</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row mb-3 align-items-end">
                <div class="col-md-3 mb-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search Bulletin No / Style No" value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control form-control-sm msfl-select2">
                        <option value="">All Status</option>
                        @foreach(\ME\MerchandisingSfl\Models\Bulletin::statusOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end flex-wrap gap-1">
                    <button type="submit" class="btn btn-sm ui-act ui-act-filter" title="Filter"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="{{ route('msfl.bulletins.index') }}" class="btn btn-sm ui-act ui-act-reset" title="Reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                    @include('merchandising-sfl::admin.partials.print-button')
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr><th>#</th><th>Bulletin No</th><th>Date</th><th>Style</th><th>Buyer</th><th>Rev</th><th>Operations</th><th>SMV</th><th>Target / Hr</th><th>Ttl MP</th><th>Utilization</th><th>Min P.Target</th><th>Status</th><th class="text-right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($bulletins as $bulletin)
                            <tr>
                                <td>{{ $loop->iteration + $bulletins->firstItem() - 1 }}</td>
                                <td>{{ $bulletin->bulletin_no }}</td>
                                <td>{{ $bulletin->bulletin_date?->format('d M Y') }}</td>
                                <td>{{ $bulletin->style?->label() ?? '-' }}</td>
                                <td>{{ $bulletin->style->buyer->name ?? '-' }}</td>
                                <td>{{ $bulletin->version }}</td>
                                <td>{{ $bulletin->active_operations_count }}</td>
                                <td>{{ number_format((float) $bulletin->total_smv, 2) }}</td>
                                <td>{{ $bulletin->target_per_hour }}</td>
                                <td>{{ $bulletin->total_manpower }} <small class="text-muted">({{ $bulletin->operators }}+{{ $bulletin->helpers }})</small></td>
                                <td>{{ round((float) $bulletin->utilization_percent) }}%</td>
                                <td>{{ $bulletin->min_p_target }}</td>
                                <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $bulletin])</td>
                                <td class="text-right">
                                    @can('msfl_bulletin.view')
                                        <a href="{{ route('msfl.bulletins.show', $bulletin) }}" class="btn-custom ui-act ui-act-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('msfl.bulletins.print', $bulletin) }}" target="_blank" class="btn-custom ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i></a>
                                    @endcan
                                    @if($bulletin->isEditable())
                                        @can('msfl_bulletin.edit')
                                            <a href="{{ route('msfl.bulletins.edit', $bulletin) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        @endcan
                                        @can('msfl_bulletin.delete')
                                            <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteBulletinModal" data-action="{{ route('msfl.bulletins.destroy', $bulletin) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="14" class="text-center text-muted">No bulletins found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $bulletins->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteBulletinModal', 'label' => 'bulletin'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
