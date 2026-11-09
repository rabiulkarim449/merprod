@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Reports') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    <div class="card">
        <div class="card-header"><h4 class="mb-0">Reports</h4></div>
        <div class="card-body">
            <div class="row">
                @foreach($reports as $key => [$title, $icon, $filters, $description])
                    <div class="col-md-4 mb-3">
                        <a href="{{ route('msfl.reports.show', $key) }}" class="text-reset text-decoration-none">
                            <div class="border rounded p-3 h-100">
                                <h6 class="mb-1"><i class="fa-solid {{ $icon }} text-danger mr-1"></i> {{ $title }}</h6>
                                <small class="text-muted">{{ $description }}</small>
                            </div>
                        </a>
                    </div>
                @endforeach
                <div class="col-md-4 mb-3">
                    <a href="{{ route('msfl.tna-sheet.index') }}" class="text-reset text-decoration-none"><div class="border rounded p-3 h-100">
                        <h6 class="mb-1"><i class="fa-solid fa-table-cells text-danger mr-1"></i> T&amp;A Sheet (81 columns)</h6><small class="text-muted">The buyer's T&amp;A sheet per PO — screen and print.</small></div></a>
                </div>
                <div class="col-md-4 mb-3">
                    <a href="{{ route('msfl.post-costing.index') }}" class="text-reset text-decoration-none"><div class="border rounded p-3 h-100">
                        <h6 class="mb-1"><i class="fa-solid fa-scale-balanced text-danger mr-1"></i> Post Cost Sheet</h6><small class="text-muted">Budget vs actual cost and CM per PO.</small></div></a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
