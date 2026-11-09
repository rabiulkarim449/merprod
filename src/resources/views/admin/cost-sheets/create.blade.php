@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Cost Sheet') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'New Cost Sheet' }}</h4>
            <a href="{{ route('msfl.cost-sheets.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.cost-sheets.store') }}" enctype="multipart/form-data">
                @csrf
                @include('merchandising-sfl::admin.cost-sheets.partials.form')
                <button type="submit" class="btn mt-3 btn-sm ui-act ui-act-save" title="Save Cost Sheet"><i class="fa-solid fa-floppy-disk"></i> Save Cost Sheet</button>
                <a href="{{ route('msfl.cost-sheets.index') }}" class="btn mt-3 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@endsection
