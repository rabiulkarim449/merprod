@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Edit ' . $fpl->fpl_no) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit Final Packing List — {{ $fpl->fpl_no }} <small class="text-muted">PO {{ $fpl->po_no }}</small></h4>
            <a href="{{ route('msfl.production.final-packing-lists.show', $fpl) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.production.final-packing-lists.update', $fpl) }}">
                @csrf @method('PUT')
                @include('merchandising-sfl::admin.production.final-packing-lists.partials.notes')
                <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Update"><i class="fa-solid fa-floppy-disk"></i> Update</button>
                <a href="{{ route('msfl.production.final-packing-lists.show', $fpl) }}" class="btn btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
