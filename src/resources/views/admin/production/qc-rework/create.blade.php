@extends(adminTheme() . 'layouts.app')

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $oldDefects = old('defects', []);
    $title = ProductionFlow::label($stage) . ' ' . ($kind === 'qc' ? 'QC' : 'Rework');
    $usesLine = ProductionFlow::usesLine($stage);
@endphp

@section('title')
    <title>{{ websiteTitle($title) }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">{{ $title }}</h4>
            <a href="{{ route('msfl.production.' . $kind . '.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            @if($pos->isEmpty())
                <div class="alert alert-warning">No confirmed order PO has {{ strtolower(ProductionFlow::label($stage)) }} on its route.</div>
            @endif
            <p class="small text-muted">{{ $kind === 'qc'
                ? 'Check the pieces this step passed (not yet taken by the next step): how many passed QC, how many are rejected (rejects leave the flow). Rework goes in Production → Rework.'
                : 'Rework found: passed pieces sent back for rework (they wait here). Rework done: how many reworked pieces passed, how many had to be rejected.' }}</p>

            <form method="POST" action="{{ route('msfl.production.' . $kind . '.store') }}">
                @csrf
                <input type="hidden" name="stage" value="{{ $stage }}">
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'entry_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => now(), 'attrs' => 'max="' . now()->format('Y-m-d') . '"'])
                    @if($partWise)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Part <span class="text-danger">*</span></label>
                            <select name="part_name" id="partInput" class="form-control form-control-sm" required>
                                <option value="">— Select part —</option>
                                @foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(old('part_name') === $gp)>{{ $gp }}</option>@endforeach
                            </select>
                            @error('part_name')<span class="form-text text-danger">{{ $message }}</span>@enderror
                        </div>
                    @endif
                    @if($usesLine)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Line <span class="text-danger">*</span></label>
                            <select name="line_id" id="lineSelect" class="form-control form-control-sm msfl-select2" required>
                                <option value="">— Select line —</option>
                                @foreach($lines as $line)<option value="{{ $line->id }}" data-code="{{ strtolower(trim($line->code)) }}" @selected(old('line_id') == $line->id)>{{ $line->name }}</option>@endforeach
                            </select>
                            @error('line_id')<span class="form-text text-danger">{{ $message }}</span>@enderror
                        </div>
                    @endif
                </div>

                @php $cols = $kind === 'qc' ? ['pass_qty' => 'QC Pass', 'reject_qty' => 'Reject'] : ['rework_qty' => 'Rework Found', 'pass_qty' => 'Passed after rework', 'reject_qty' => 'Reject']; @endphp
                <h6>Sizes <small class="text-muted">— every size of the picked PO{{ $partWise ? ' (balances of the picked part)' : '' }}</small></h6>
                @if($errors->has('sizes') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'sizes.')))
                    <div class="alert alert-danger py-2 small mb-2">{!! collect($errors->getMessages())->filter(fn ($m, $k) => $k === 'sizes' || str_starts_with($k, 'sizes.'))->flatten()->map(fn ($m) => e($m))->implode('<br>') !!}</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-2">
                        <thead><tr><th style="width:70px">Size</th><th class="text-right">Passed, still here{{ $kind === 'qc' ? ' (can check)' : ' (rework found up to)' }}</th>@if($kind === 'rework')<th class="text-right">In rework now</th>@endif
                            @foreach($cols as $f => $l)<th style="width:120px">{{ $l }}</th>@endforeach</tr></thead>
                        <tbody id="sizeRowsBody"><tr><td colspan="{{ 2 + ($kind === 'rework' ? 1 : 0) + count($cols) }}" class="text-center text-muted">Pick a style · color.</td></tr></tbody>
                        <tfoot><tr class="font-weight-bold"><td colspan="{{ 2 + ($kind === 'rework' ? 1 : 0) }}" class="text-right">Total</td>@foreach($cols as $f => $l)<td data-col-total="{{ $f }}">0</td>@endforeach</tr></tfoot>
                    </table>
                </div>
                <div class="row">@include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6])</div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">{{ $kind === 'qc' ? 'Reject Detail' : 'Rework / Reject Detail' }} <small class="text-muted">(per size, must add up to that size's {{ $kind === 'qc' ? 'reject' : 'reject / rework found' }} — <span data-defect-check></span>)</small></h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="defect" title="Add Row"><i class="fa-solid fa-plus"></i> Add Row</button>
                </div>
                @error('defects')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th style="width:90px">Size</th><th style="width:120px">Type</th><th style="width:120px">Severity <span class="text-danger">*</span></th><th>Part</th><th>Machine <small class="text-muted">(optional)</small></th><th>Defect</th><th style="width:110px">Pcs</th><th style="width:40px"></th></tr></thead>
                        <tbody id="defectRowsBody">
                            @foreach($oldDefects ?: [[]] as $i => $d)
                                @include('merchandising-sfl::admin.production.entries.defect-row', ['index' => $i, 'd' => $d, 'withSize' => true])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="defectRowTemplate">
                    @include('merchandising-sfl::admin.production.entries.defect-row', ['index' => '__INDEX__', 'd' => [], 'withSize' => true])
                </template>

                <button type="submit" class="btn mt-2 btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <button type="submit" name="add_another" value="1" class="btn mt-2 btn-sm ui-act ui-act-save" title="Save & Add Another"><i class="fa-solid fa-floppy-disk"></i> Save &amp; Add Another</button>
                <a href="{{ route('msfl.production.' . $kind . '.index') }}" class="btn mt-2 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
            </form>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.line-items-script')
@include('merchandising-sfl::admin.partials.select2-init')
@endsection

@push('js')
<script>
(function () {
    const balances = @json($balances);
    const poSizes = @json($poSizes);
    const sizeNames = @json($sizeNames);
    const kind = @json($kind);
    const cols = @json(array_keys($cols));
    const oldSizes = @json(old('sizes', []));
    const poSelect = document.getElementById('poSelect');
    const partInput = document.getElementById('partInput');
    const lineSelect = document.getElementById('lineSelect');
    const body = document.getElementById('sizeRowsBody');
    const jq = typeof $ !== 'undefined' ? $ : null;

    function bal(id) {
        let b = (balances[poSelect.value] || {})[id];
        if (b && partInput) {
            const part = partInput.value.trim();
            b = part ? ((b.parts || {})[part] || {ready: 0, wip: 0}) : null;
        }
        return b;
    }

    function build() {
        const ids = poSizes[poSelect.value] || [];
        const span = 2 + (kind === 'rework' ? 1 : 0) + cols.length;
        body.innerHTML = ids.length ? '' : '<tr><td colspan="' + span + '" class="text-center text-muted">Pick a style · color.</td></tr>';
        ids.forEach(function (id) {
            const b = bal(id);
            const tr = document.createElement('tr');
            tr.dataset.size = id;
            let html = '<td><strong>' + (sizeNames[id] || id) + '</strong></td><td class="text-right font-weight-bold">' + (b ? b.ready : (partInput ? '<span class="text-muted">pick part</span>' : 0)) + '</td>';
            if (kind === 'rework') html += '<td class="text-right">' + (b ? b.wip : '') + '</td>';
            cols.forEach(function (c) {
                html += '<td><input type="number" min="0" step="1" name="sizes[' + id + '][' + c + ']" value="' + ((oldSizes[id] || {})[c] || '') + '" class="form-control form-control-sm text-right" data-col="' + c + '"></td>';
            });
            tr.innerHTML = html;
            body.appendChild(tr);
        });
        refreshDefectSizes();
        totals();
    }

    function totals() {
        cols.forEach(function (c) {
            let t = 0;
            body.querySelectorAll('[data-col="' + c + '"]').forEach(i => t += parseInt(i.value || 0, 10));
            const el = document.querySelector('[data-col-total="' + c + '"]');
            if (el) el.textContent = t;
        });
        body.querySelectorAll('tr[data-size]').forEach(function (row) {
            const b = bal(row.dataset.size) || {ready: 0, wip: 0};
            const v = c => parseInt(row.querySelector('[data-col="' + c + '"]')?.value || 0, 10);
            const over = kind === 'qc' ? v('pass_qty') + v('reject_qty') > b.ready
                : (v('rework_qty') > b.ready || v('pass_qty') + v('reject_qty') > b.wip + v('rework_qty'));
            row.querySelectorAll('[data-col]').forEach(i => i.classList.toggle('is-invalid', over));
        });
        defectCheck();
    }

    function refreshDefectSizes(scope) {
        const ids = poSizes[poSelect.value] || [];
        (scope || document).querySelectorAll('[data-defect-size]').forEach(function (sel) {
            const cur = sel.value || sel.dataset.old || '';
            sel.innerHTML = '<option value="">— Size —</option>' + ids.map(id => '<option value="' + id + '"' + (String(id) === String(cur) ? ' selected' : '') + '>' + (sizeNames[id] || id) + '</option>').join('');
        });
        // QC screen: reject rows only (rework is entered on the Rework screen).
        document.querySelectorAll('[data-defect-type] option[value="rework"]').forEach(o => {
            o.disabled = kind === 'qc';
            if (o.disabled && o.selected) o.parentElement.value = 'reject';
        });
    }

    function defectCheck() {
        const out = document.querySelector('[data-defect-check]');
        if (! out) return;
        const want = {}, got = {};
        body.querySelectorAll('tr[data-size]').forEach(function (row) {
            [['reject_qty', 'reject'], ['rework_qty', 'rework']].forEach(function ([c, t]) {
                const v = parseInt(row.querySelector('[data-col="' + c + '"]')?.value || 0, 10);
                if (v) want[row.dataset.size + '|' + t] = v;
            });
        });
        document.querySelectorAll('#defectRowsBody tr').forEach(function (row) {
            const q = parseInt(row.querySelector('[data-defect-qty]').value || 0, 10);
            if (! q) return;
            const k = (row.querySelector('[data-defect-size]').value || '?') + '|' + row.querySelector('[data-defect-type]').value;
            got[k] = (got[k] || 0) + q;
        });
        const bad = [...new Set([...Object.keys(want), ...Object.keys(got)])].filter(k => (want[k] || 0) !== (got[k] || 0));
        out.textContent = bad.length ? 'not matching: ' + bad.map(k => { const [s, t] = k.split('|'); return (sizeNames[s] || s) + ' ' + t + ' ' + (got[k] || 0) + '/' + (want[k] || 0); }).join(', ') : '✓';
        out.className = bad.length ? 'text-danger' : 'text-success';
    }

    // Sewing: show only the Inventory machines on the chosen line.
    function filterMachines(scope) {
        if (! lineSelect) return;
        const code = lineSelect.selectedOptions[0]?.dataset.code || '';
        (scope || document).querySelectorAll('[data-machine-select]').forEach(function (sel) {
            Array.from(sel.options).forEach(function (o) {
                if (! o.value) return;
                const on = ! code || o.dataset.line === code || o.dataset.line === '';
                o.disabled = ! on;
                if (! on && o.selected) sel.value = '';
            });
        });
    }

    body.addEventListener('input', totals);
    if (partInput) partInput.addEventListener('change', build);
    if (jq) { jq(poSelect).on('change', build); if (lineSelect) jq(lineSelect).on('change', () => filterMachines()); }
    document.addEventListener('input', function (e) { if (e.target.closest('#defectRowsBody')) defectCheck(); });
    document.addEventListener('change', function (e) { if (e.target.closest('#defectRowsBody')) defectCheck(); });
    document.getElementById('defectRowsBody').addEventListener('msfl:rows-changed', function (e) { refreshDefectSizes(e.detail?.row); filterMachines(e.detail?.row); defectCheck(); });
    build(); filterMachines();
})();
</script>
@endpush
