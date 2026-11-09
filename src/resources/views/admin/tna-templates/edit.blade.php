@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('T&A Template — ' . $template->name) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">T&amp;A Template — {{ $template->name }}</h4>
            <a href="{{ route('msfl.tna-templates.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.tna-templates.update', $template) }}">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.tna-templates.partials.header-fields')

                <div class="alert alert-light border small">
                    <strong>How dates are worked out (working days, Fridays &amp; holidays skipped):</strong>
                    Ex-Factory = Shipment − {{ $template->ship_to_ex_factory_days }} ·
                    Sewing End = Ex-Factory − {{ $template->ex_factory_to_sewing_end_days }} ·
                    Sewing Start = Sewing End − (Order Qty ÷ daily line capacity) ·
                    PCD = Sewing Start − {{ $template->pcd_to_sewing_start_days }}.
                    Each step below = its <em>anchor</em> ± <em>days</em> (negative = before). “Auto” fills the actual date by itself.
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Steps</h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="tpl" title="Add Step"><i class="fa-solid fa-plus"></i> Add Step</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th></th><th>Group</th><th>Code</th><th>Step</th><th>Anchor</th><th>± Days</th><th>Actual date from</th><th>Applies</th><th>Must</th><th></th></tr></thead>
                        <tbody id="tplRowsBody">
                            @php $tasks = old('tasks', $template->tasks->toArray()); @endphp
                            @foreach(empty($tasks) ? [[]] : $tasks as $index => $task)
                                @include('merchandising-sfl::admin.tna-templates.partials.task-row', ['index' => $index, 'task' => $task])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <datalist id="tnaGroups">
                    @foreach(['Order', 'Sample', 'Material', 'Pre-Production', 'Production', 'Shipment'] as $group)<option value="{{ $group }}">@endforeach
                </datalist>
                <template id="tplRowTemplate">
                    @include('merchandising-sfl::admin.tna-templates.partials.task-row', ['index' => '__INDEX__', 'task' => []])
                </template>

                <button type="submit" class="btn btn-sm mt-2 ui-act ui-act-save" title="Save Template"><i class="fa-solid fa-floppy-disk"></i> Save Template</button>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.line-items-script')
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
