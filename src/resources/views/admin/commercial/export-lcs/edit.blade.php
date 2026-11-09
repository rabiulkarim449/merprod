@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Edit Export LC — ' . $lc->lc_no . '') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ 'Edit Export LC — ' . $lc->lc_no . '' }}</h4>
            <a href="{{ route('msfl.commercial.export-lcs.show', $lc) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            @if($lc->status === 'active')
                <div class="alert alert-info py-2 small">This LC is active — a change of LC value, last shipment or expiry date is saved as an <strong>amendment</strong> (history on the LC page).</div>
            @endif
            <form method="POST" action="{{ route('msfl.commercial.export-lcs.update', $lc) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.commercial.export-lcs.partials.form')
                <button type="submit" class="btn mt-3 btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="{{ route('msfl.commercial.export-lcs.show', $lc) }}" class="btn mt-3 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
