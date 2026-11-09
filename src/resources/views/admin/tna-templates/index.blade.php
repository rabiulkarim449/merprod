@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'T&A Templates' }}@else
    <title>{{ websiteTitle('T&A Templates') }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'T&A Templates', 'printPage' => 'A4 landscape'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">T&amp;A Templates</h4>
            <div>
                @include('merchandising-sfl::admin.partials.print-button')
                @can('msfl_tna_template.add')
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-toggle="modal" data-target="#createTemplateModal" title="New Template"><i class="fa-solid fa-plus"></i> New Template</button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                A template is the list of T&amp;A steps and when each one falls (e.g. “PP Approval = PCD − 10 working days”).
                The sewing days come from capacity; everything else comes from here. Changing a template affects only T&amp;As created afterwards.
            </p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead><tr><th>#</th><th>Name</th><th>Steps</th><th>Shipment → Ex-Factory</th><th>Ex-Factory → Sewing End</th><th>PCD → Sewing Start</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $template->name }} @if($template->is_default)<span class="badge badge-primary">Default</span>@endif</td>
                                <td>{{ $template->tasks_count }}</td>
                                <td>{{ $template->ship_to_ex_factory_days }} days</td>
                                <td>{{ $template->ex_factory_to_sewing_end_days }} days</td>
                                <td>{{ $template->pcd_to_sewing_start_days }} days</td>
                                <td><span class="badge badge-{{ $template->is_active ? 'success' : 'secondary' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-right">
                                    @can('msfl_tna_template.edit')
                                        <a href="{{ route('msfl.tna-templates.edit', $template) }}" class="btn-custom ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    @endcan
                                    @can('msfl_tna_template.delete')
                                        <button type="button" class="btn-custom ui-act ui-act-delete" data-toggle="modal" data-target="#deleteTemplateModal" data-action="{{ route('msfl.tna-templates.destroy', $template) }}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No templates yet — create one, or run the default seeder.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@can('msfl_tna_template.add')
    <div class="modal fade" id="createTemplateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('msfl.tna-templates.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">New T&amp;A Template</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @include('merchandising-sfl::admin.tna-templates.partials.header-fields', ['template' => null])
                        <div class="row">
                            @include('merchandising-sfl::admin.partials.select', ['name' => 'copy_from', 'label' => 'Copy steps from', 'col' => 6, 'placeholder' => '— Start empty —', 'options' => $templates->pluck('name', 'id'), 'value' => $templates->firstWhere('is_default', true)?->id])
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Create"><i class="fa-solid fa-floppy-disk"></i> Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcan

@include('merchandising-sfl::admin.partials.delete-confirm-modal', ['modalId' => 'deleteTemplateModal', 'label' => 'template'])
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
