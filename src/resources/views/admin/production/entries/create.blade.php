@extends(adminTheme() . 'layouts.app')

@php
    use ME\MerchandisingSfl\Services\ProductionFlow;
    $label = ProductionFlow::label($stage);
    $usesLine = ProductionFlow::usesLine($stage);
    $oldDefects = old('defects', []);
    // Reject / rework go through Production → QC / Rework; only Buyer QC (ready-product QC) records them here.
    $isQc = $stage === 'final_qc';
    // Embroidery / washing: one form sends (input), the other receives back (output).
    $split = $mode !== null ? ProductionFlow::splitLabels($stage) : null;
    $heading = $split ? $split[$mode === 'input' ? 0 : 1] : 'New ' . $label . ' Entry';
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
            @if($pos->isEmpty())
                <div class="alert alert-warning">No confirmed order PO has {{ strtolower($label) }} on its route{{ in_array($stage, ['embroidery', 'washing']) ? ' — tick "Needs ' . $label . '" on the order PO' : '' }}.</div>
            @endif
            <form method="POST" action="{{ route('msfl.production.entries.store', ['stage' => $stage]) }}">
                @csrf
                @if($mode)<input type="hidden" name="mode" value="{{ $mode }}">@endif
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'entry_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => now(), 'attrs' => 'max="' . now()->format('Y-m-d') . '"'])
                    @if($partWise)
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Part <span class="text-danger">*</span> <small class="text-muted">(sent from cutting)</small></label>
                            <select name="part_name" id="partInput" class="form-control form-control-sm" required>
                                <option value="">— Select part —</option>
                                @foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(old('part_name') === $gp)>{{ $gp }}</option>@endforeach
                            </select>
                            <span class="form-text text-muted small" id="poPartHint"></span>
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
                            @if($lines->isEmpty())<span class="form-text text-danger">No line set up — Planning → Setup → Lines.</span>@endif
                        </div>
                    @endif
                </div>

                @php
                    $cols = $isQc ? ['input_qty' => 'Input', 'pass_qty' => 'QC Pass', 'rework_qty' => 'Rework', 'reject_qty' => 'Reject']
                        : ($mode === 'input' ? ['input_qty' => ($stage === 'embroidery' ? 'Send (pcs)' : ($split[0] ?? 'Input') . ' (pcs)')]
                        : ($mode === 'output' ? ['pass_qty' => ($stage === 'embroidery' ? 'Returned (pcs)' : ($split[1] ?? 'Output') . ' (pcs)')]
                        : ['input_qty' => 'Input', 'pass_qty' => 'Output']));
                @endphp
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                    <h6 class="mb-0">Sizes <small class="text-muted" id="sizeHint">— pick a style · color above; every size of the PO shows here</small></h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-auto" id="fillAll" title="Put the most each size can take"><i class="fa-solid fa-wand-magic-sparkles"></i> Fill all</button>
                </div>
                @if($errors->has('sizes') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'sizes.')))
                    <div class="alert alert-danger py-2 small mb-2">{!! collect($errors->getMessages())->filter(fn ($m, $k) => $k === 'sizes' || str_starts_with($k, 'sizes.'))->flatten()->map(fn ($m) => e($m))->implode('<br>') !!}</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-2">
                        <thead><tr><th style="width:70px">Size</th><th class="text-right">Order</th><th class="text-right" id="availHead">Can take in</th><th class="text-right">In {{ strtolower($label) }} now</th><th class="text-right">So far (in / out)</th>
                            @foreach($cols as $f => $l)<th style="width:120px">{{ $l }}</th>@endforeach</tr></thead>
                        <tbody id="sizeRowsBody"><tr><td colspan="{{ 5 + count($cols) }}" class="text-center text-muted">Pick a style · color.</td></tr></tbody>
                        <tfoot><tr class="font-weight-bold"><td colspan="5" class="text-right">Total</td>@foreach($cols as $f => $l)<td data-col-total="{{ $f }}">0</td>@endforeach</tr></tfoot>
                    </table>
                </div>
                <div class="row">
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6])
                </div>
                @unless($isQc)
                    <p class="small text-muted mt-n2">Reject / rework of {{ strtolower($label) }} go in <strong>Production → QC</strong> and <strong>Production → Rework</strong>.</p>
                @endunless
                @if($isQc)
                <p class="small text-muted mt-n2">Rework pieces stay in {{ strtolower($label) }} until they pass; rejects leave the flow. Break every reject / rework down below — part and machine are optional (pick the machine to see machine-wise rejection).</p>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Reject / Rework Detail <small class="text-muted">(per size, must add up to that size's reject / rework — <span data-defect-check></span>)</small></h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="defect" title="Add Row"><i class="fa-solid fa-plus"></i> Add Row</button>
                </div>
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
                @endif

                <button type="submit" class="btn mt-2 btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <button type="submit" name="add_another" value="1" class="btn mt-2 btn-sm ui-act ui-act-save" title="Save & Add Another"><i class="fa-solid fa-floppy-disk"></i> Save &amp; Add Another</button>
                <a href="{{ route('msfl.production.entries.index', ['stage' => $stage]) }}" class="btn mt-2 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
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
    const poParts = @json($poParts);
    const poSizes = @json($poSizes);
    const poOrdered = @json($poOrdered);
    const sizeNames = @json($sizeNames);
    const cols = @json(array_keys($cols));
    const mode = @json($mode);
    const isQc = @json($isQc);
    const oldSizes = @json(old('sizes', []));
    const partInput = document.getElementById('partInput');
    const poSelect = document.getElementById('poSelect');
    const body = document.getElementById('sizeRowsBody');
    const jq = typeof $ !== 'undefined' ? $ : null;

    // Balance of one size (part-wise stages: that part's balance).
    function bal(sizeId) {
        let b = (balances[poSelect.value] || {})[sizeId];
        if (b && partInput) {
            const part = partInput.value.trim();
            b = part ? ((b.parts || {})[part] || b.newPart) : null;
        }
        return b;
    }
    // Most a size can get in a column: input ≤ can take in; output / pass ≤ in the stage (+ what comes in now).
    function maxFor(b, col, row) {
        if (! b) return 0;
        if (col === 'input_qty') return b.available;
        const comingIn = row ? (parseInt(row.querySelector('[data-col="input_qty"]')?.value || 0, 10)) : 0;
        return b.wip + Math.min(comingIn, b.available);
    }

    function build() {
        const ids = poSizes[poSelect.value] || [];
        if (partInput) {
            const cut = poParts[poSelect.value] || [];
            document.getElementById('poPartHint').textContent = cut.length ? 'Cut for this PO: ' + cut.join(', ') : '';
        }
        body.innerHTML = '';
        if (! ids.length) {
            body.innerHTML = '<tr><td colspan="' + (5 + cols.length) + '" class="text-center text-muted">Pick a style · color.</td></tr>';
            return totals();
        }
        ids.forEach(function (id) {
            const b = bal(id);
            const tr = document.createElement('tr');
            tr.dataset.size = id;
            let html = '<td><strong>' + (sizeNames[id] || id) + '</strong></td>'
                + '<td class="text-right">' + ((poOrdered[poSelect.value] || {})[id] || 0) + '</td>'
                + '<td class="text-right font-weight-bold">' + (b ? b.available : (partInput ? '<span class="text-muted">pick part</span>' : 0)) + '</td>'
                + '<td class="text-right">' + (b ? b.wip : '') + '</td>'
                + '<td class="text-right small text-muted">' + (b ? b.input + ' / ' + b.pass + (b.reject ? ' · rej ' + b.reject : '') : '') + '</td>';
            cols.forEach(function (c) {
                const v = (oldSizes[id] || {})[c] || '';
                html += '<td><input type="number" min="0" step="1" name="sizes[' + id + '][' + c + ']" value="' + v + '" class="form-control form-control-sm text-right" data-col="' + c + '"></td>';
            });
            tr.innerHTML = html;
            body.appendChild(tr);
        });
        totals();
        refreshDefectSizes();
    }

    function totals() {
        cols.forEach(function (c) {
            let t = 0;
            body.querySelectorAll('[data-col="' + c + '"]').forEach(function (i) {
                t += parseInt(i.value || 0, 10);
                const row = i.closest('tr');
                const over = (parseInt(i.value || 0, 10)) > maxFor(bal(row.dataset.size), c, row) && (c === 'input_qty' || c === 'pass_qty');
                i.classList.toggle('is-invalid', over);
            });
            const el = document.querySelector('[data-col-total="' + c + '"]');
            if (el) el.textContent = t;
        });
        defectCheck();
    }

    // Fill all: each size gets what it can take (input) or what is in the stage (output).
    document.getElementById('fillAll').addEventListener('click', function () {
        body.querySelectorAll('tr[data-size]').forEach(function (row) {
            const b = bal(row.dataset.size);
            const input = row.querySelector('[data-col="' + (mode === 'output' ? 'pass_qty' : 'input_qty') + '"]');
            if (input && b) input.value = (mode === 'output' ? b.wip : b.available) || '';
        });
        totals();
    });

    // Buyer QC: defect rows carry the size; per size they must add up to its reject / rework.
    function refreshDefectSizes(scope) {
        const ids = poSizes[poSelect.value] || [];
        (scope || document).querySelectorAll('[data-defect-size]').forEach(function (sel) {
            const cur = sel.value || sel.dataset.old || '';
            sel.innerHTML = '<option value="">— Size —</option>' + ids.map(id => '<option value="' + id + '"' + (String(id) === String(cur) ? ' selected' : '') + '>' + (sizeNames[id] || id) + '</option>').join('');
        });
    }
    function defectCheck() {
        const out = document.querySelector('[data-defect-check]');
        if (! out) return;
        const want = {}, got = {};
        body.querySelectorAll('tr[data-size]').forEach(function (row) {
            ['reject_qty', 'rework_qty'].forEach(function (c) {
                const v = parseInt(row.querySelector('[data-col="' + c + '"]')?.value || 0, 10);
                if (v) want[row.dataset.size + '|' + c.replace('_qty', '')] = v;
            });
        });
        document.querySelectorAll('#defectRowsBody tr').forEach(function (row) {
            const q = parseInt(row.querySelector('[data-defect-qty]').value || 0, 10);
            if (! q) return;
            const k = (row.querySelector('[data-defect-size]').value || '?') + '|' + row.querySelector('[data-defect-type]').value;
            got[k] = (got[k] || 0) + q;
        });
        const keys = new Set([...Object.keys(want), ...Object.keys(got)]);
        const bad = [...keys].filter(k => (want[k] || 0) !== (got[k] || 0));
        out.textContent = bad.length ? 'not matching: ' + bad.map(k => { const [s, t] = k.split('|'); return (sizeNames[s] || s) + ' ' + t + ' ' + (got[k] || 0) + '/' + (want[k] || 0); }).join(', ') : '✓';
        out.className = bad.length ? 'text-danger' : 'text-success';
    }

    // Sewing: show only the Inventory machines on the chosen line.
    const lineSelect = document.getElementById('lineSelect');
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
            if (jq) jq(sel).trigger('change.select2');
        });
    }

    body.addEventListener('input', totals);
    if (partInput) partInput.addEventListener('change', build);
    if (jq) { jq(poSelect).on('change', build); if (lineSelect) jq(lineSelect).on('change', () => filterMachines()); }
    document.addEventListener('input', function (e) { if (e.target.closest('#defectRowsBody')) defectCheck(); });
    document.addEventListener('change', function (e) { if (e.target.closest('#defectRowsBody')) defectCheck(); });
    document.getElementById('defectRowsBody')?.addEventListener('msfl:rows-changed', function (e) { refreshDefectSizes(e.detail?.row); filterMachines(e.detail?.row); defectCheck(); });
    build(); filterMachines();
})();
</script>
@endpush
