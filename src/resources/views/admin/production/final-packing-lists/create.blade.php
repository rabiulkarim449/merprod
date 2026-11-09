@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Final Packing List') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New Final Packing List</h4>
            <a href="{{ route('msfl.production.final-packing-lists.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="GET" class="row align-items-end mb-2">
                <div class="col-md-8 mb-2">
                    <label class="form-label">Buyer PO <span class="text-danger">*</span> <small class="text-muted">(confirmed — all styles of the PO come together)</small></label>
                    <select name="po" class="form-control form-control-sm msfl-select2" onchange="this.form.submit()">
                        <option value="">— Select —</option>
                        @foreach($choices as $c)<option value="{{ $c['key'] }}" @selected(($picked['key'] ?? null) === $c['key'])>{{ $c['label'] }}</option>@endforeach
                    </select>
                </div>
            </form>

            @if($sheet)
                <form method="POST" action="{{ route('msfl.production.final-packing-lists.store') }}">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $picked['order_id'] }}">
                    <input type="hidden" name="po_no" value="{{ $picked['po_no'] }}">
                    @include('merchandising-sfl::admin.production.final-packing-lists.partials.notes')
                    <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                    <a href="{{ route('msfl.production.final-packing-lists.index') }}" class="btn btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
                </form>
                <hr>
                <h6>Preview</h6>
                <div class="table-responsive"><div style="min-width:760px">@include('merchandising-sfl::admin.production.final-packing-lists.partials.sheet', ['print' => false])</div></div>
            @else
                <p class="small text-muted mb-0">Pick a buyer PO — order, cutting and packed (ship) qty per size come from production.</p>
            @endif
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
