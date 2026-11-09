@php
    $printMode = request()->boolean('print');
    $slug = $definition['slug'];
    $perm = $definition['permission'];
    $title = $definition['singular'] . ' — ' . ($model->{collect($definition['fields'])->firstWhere('type', 'text')['name'] ?? 'id'} ?: '#' . $model->id);
@endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ $title }}@else
    <title>{{ websiteTitle($title) }}</title>
@endif
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => $title, 'printPage' => 'A4 portrait'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h4 class="mb-0">{{ $title }}</h4>
            <div>
                @include('merchandising-sfl::admin.partials.print-button')
                @can($perm . '.edit')<a href="{{ route("msfl.commercial.$slug.edit", $model->id) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>@endcan
                <a href="{{ route("msfl.commercial.$slug.index") }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                @foreach($definition['fields'] as $field)
                    @continue(in_array($field['type'], ['pick', 'file', 'textarea'], true))
                    <div class="col-md-4 mb-2"><strong>{{ $field['label'] }}:</strong> {{ \ME\MerchandisingSfl\Support\CommercialRecordRegistry::display($field, $model) }}</div>
                @endforeach
                @foreach($definition['fields'] as $field)
                    @if($field['type'] === 'file' && ! $printMode)
                        @include('merchandising-sfl::admin.commercial.partials.attachments-list', ['value' => $model->{$field['name']}, 'label' => $field['label']])
                    @endif
                @endforeach
                @foreach($definition['fields'] as $field)
                    @if($field['type'] === 'textarea')
                        <div class="col-md-12 mb-2"><strong>{{ $field['label'] }}:</strong> {{ \ME\MerchandisingSfl\Support\CommercialRecordRegistry::display($field, $model) }}</div>
                    @endif
                @endforeach
                <div class="col-md-12 mb-2 small text-muted">Entered by {{ $model->creator->name ?? '-' }} on {{ $model->created_at?->format('d M Y h:i A') }}</div>
            </div>

            @if(isset($definition['totals']) && ($totals = ($definition['totals'])($model)))
                <div class="row mb-3">
                    @foreach($totals as $label => $value)
                        <div class="col-md-4 mb-2"><div class="border rounded p-2"><small class="text-muted d-block">{{ $label }}</small><strong>{{ $value }}</strong></div></div>
                    @endforeach
                </div>
            @endif

            @foreach(isset($definition['related']) ? ($definition['related'])($model) : [] as $block)
                <h6 class="mt-3">{{ $block['title'] }}</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>#</th>@foreach($block['head'] as $head)<th>{{ $head }}</th>@endforeach</tr></thead>
                        <tbody>
                            @forelse($block['rows'] as $row)
                                <tr><td>{{ $loop->iteration }}</td>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                            @empty
                                <tr><td colspan="{{ count($block['head']) + 1 }}" class="text-center text-muted">None yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
