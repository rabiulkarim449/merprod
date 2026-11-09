@extends(adminTheme() . 'layouts.app')

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $label = ProductionFlow::label($stage);
    $oldDefects = old('defects', $entry->defects->map(fn ($d) => ['type' => $d->type, 'severity' => $d->severity, 'part_name' => $d->part_name, 'machine_id' => $d->machine_id, 'defect' => $d->defect, 'qty' => $d->qty])->all());
    $heading = $label . ' Entry — Edit';
@endphp

@section('title')
    <title>{{ websiteTitle($heading) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $heading }}</h4>
            <a href="{{ route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <dl class="row mb-3 small">
                <dt class="col-sm-2">Order / PO</dt><dd class="col-sm-4">{{ $entry->orderPo->order->order_no ?? '' }} · {{ $entry->orderPo->po_no ?? '' }} ({{ $entry->orderPo->order->buyer->name ?? '' }})</dd>
                <dt class="col-sm-2">Style · Colour</dt><dd class="col-sm-4">{{ $entry->orderPo->style->style_no ?? '' }} · {{ $entry->orderPo->color->name ?? '' }}</dd>
                <dt class="col-sm-2">Size</dt><dd class="col-sm-4">{{ $entry->size->name ?? 'All' }}</dd>
                @if($entry->part_name)<dt class="col-sm-2">Part</dt><dd class="col-sm-4">{{ $entry->part_name }}</dd>@endif
                @if($entry->line)<dt class="col-sm-2">Line</dt><dd class="col-sm-4">{{ $entry->line->name }}</dd>@endif
            </dl>
            <p class="small text-muted mt-n2 mb-3">PO, size, part and line can't be changed here — delete and re-add the entry if those are wrong.</p>

            <form method="POST" action="{{ route('msfl.production.entries.update', ['stage' => $stage, 'entry' => $entry]) }}">
                @csrf
                @method('PUT')
                <div class="row">
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'entry_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => old('entry_date', $entry->entry_date->format('Y-m-d')), 'attrs' => 'max="' . now()->format('Y-m-d') . '"'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'input_qty', 'label' => $split ? $split[0] : 'Input (pcs in)', 'type' => 'number', 'value' => old('input_qty', $entry->input_qty)])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'pass_qty', 'label' => $isQc ? 'QC Pass' : ($split ? $split[1] : 'Output (done)'), 'type' => 'number', 'value' => old('pass_qty', $entry->pass_qty)])
                    @if($isQc)
                        @include('merchandising-sfl::admin.partials.input', ['name' => 'rework_qty', 'label' => 'Rework', 'type' => 'number', 'value' => old('rework_qty', $entry->rework_qty), 'attrs' => 'data-qty data-defect-total="rework"'])
                        @include('merchandising-sfl::admin.partials.input', ['name' => 'reject_qty', 'label' => 'Reject', 'type' => 'number', 'value' => old('reject_qty', $entry->reject_qty), 'attrs' => 'data-qty data-defect-total="reject"'])
                    @endif
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6, 'value' => old('remarks', $entry->remarks)])
                </div>
                @unless($isQc)
                    <p class="small text-muted mt-n2">Reject / rework of {{ strtolower($label) }} go in <strong>Production → QC</strong> and <strong>Production → Rework</strong>.</p>
                @endunless
                @if($isQc)
                <p class="small text-muted mt-n2">Rework pieces stay in {{ strtolower($label) }} until they pass; rejects leave the flow. Break every reject / rework down below.</p>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Reject / Rework Detail <small class="text-muted">(reject <span data-defect-sum="reject">0</span>, rework <span data-defect-sum="rework">0</span>)</small></h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="defect" title="Add Row"><i class="fa-solid fa-plus"></i> Add Row</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th style="width:120px">Type</th><th style="width:120px">Severity <span class="text-danger">*</span></th><th>Part</th><th>Machine <small class="text-muted">(optional)</small></th><th>Defect</th><th style="width:110px">Pcs</th><th style="width:40px"></th></tr></thead>
                        <tbody id="defectRowsBody">
                            @foreach($oldDefects ?: [[]] as $i => $d)
                                @include('merchandising-sfl::admin.production.entries.defect-row', ['index' => $i, 'd' => $d])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="defectRowTemplate">
                    @include('merchandising-sfl::admin.production.entries.defect-row', ['index' => '__INDEX__', 'd' => []])
                </template>
                @endif

                <button type="submit" class="btn mt-2 btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <a href="{{ route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn mt-2 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.line-items-script')
@endsection

@push('js')
<script>
(function () {
    function defectSums() {
        ['reject', 'rework'].forEach(function (type) {
            let sum = 0;
            document.querySelectorAll('#defectRowsBody tr').forEach(function (row) {
                if (row.querySelector('[data-defect-type]').value === type) sum += parseInt(row.querySelector('[data-defect-qty]').value || 0, 10);
            });
            const el = document.querySelector('[data-defect-sum="' + type + '"]');
            if (! el) return;
            const want = parseInt(document.querySelector('[data-defect-total="' + type + '"]').value || 0, 10);
            el.textContent = sum + (sum !== want ? ' ≠ ' + want : ' ✓');
            el.className = sum === want ? 'text-success' : 'text-danger';
        });
    }
    document.addEventListener('input', function (e) { if (e.target.closest('#defectRowsBody') || e.target.matches('[data-defect-total]')) defectSums(); });
    document.addEventListener('change', function (e) { if (e.target.matches('[data-defect-type]')) defectSums(); });
    document.getElementById('defectRowsBody')?.addEventListener('msfl:rows-changed', defectSums);
    defectSums();
})();
</script>
@endpush
