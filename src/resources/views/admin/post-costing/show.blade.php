@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Post Cost — ' . $c['po']->po_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Post Cost Sheet — {{ $c['po']->label() }}</h4>
            <div class="d-flex gap-1">
                <a href="{{ route('msfl.post-costing.print', $c['po']) }}" target="_blank" class="btn btn-sm ui-act ui-act-print" title="Print"><i class="fa-solid fa-print"></i> Print</a>
                <a href="{{ route('msfl.post-costing.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">@include('merchandising-sfl::admin.post-costing.partials.sheet', ['forPrint' => false])</div>
    </div>
</div>
@endsection
