{{-- props: bulletin (model, may be unsaved), operations (array), styles, lines, machineTypes, operationOptions, bulletins --}}
@php $rows = old('operations', $operations); @endphp
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'style_id', 'label' => 'Style No', 'required' => true, 'col' => 3, 'options' => $styles->mapWithKeys(fn ($s) => [$s->id => $s->label()]), 'value' => $bulletin->style_id])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'description', 'label' => 'Description', 'col' => 5, 'value' => $bulletin->description])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'bulletin_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 2, 'value' => $bulletin->bulletin_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'target_per_hour', 'label' => 'Target / Hr', 'type' => 'number', 'required' => true, 'col' => 2, 'value' => $bulletin->target_per_hour, 'attrs' => 'data-b-target step=1'])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'working_hours', 'label' => 'Working Hours / Day', 'type' => 'number', 'required' => true, 'col' => 2, 'value' => $bulletin->working_hours, 'attrs' => 'data-b-hours'])
    <div class="col-md-3 mb-3">
        <label class="form-label">Reference Line <small class="text-muted">(machine check)</small></label>
        <select name="line_id" class="form-control form-control-sm msfl-select2" data-bulletin-line>
            <option value="">— None —</option>
            @foreach($lines as $line)
                <option value="{{ $line->id }}" data-hours="{{ round($line->working_minutes / 60, 1) }}" @selected((string) old('line_id', $bulletin->line_id) === (string) $line->id)>{{ $line->name }}</option>
            @endforeach
        </select>
    </div>
    @unless($bulletin->exists)
        <div class="col-md-3 mb-3">
            <label class="form-label">Copy operations from</label>
            <select class="form-control form-control-sm msfl-select2" data-bulletin-copy>
                <option value="">— Choose a bulletin —</option>
                @foreach($bulletins as $other)
                    <option value="{{ route('msfl.bulletins.create', ['copy_from' => $other->id, 'style_id' => $bulletin->style_id]) }}">{{ $other->bulletin_no }} — {{ $other->style->style_no ?? '' }} v{{ $other->version }}</option>
                @endforeach
            </select>
        </div>
    @endunless
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => $bulletin->exists ? 7 : 4, 'value' => $bulletin->remarks])
</div>

<div class="table-responsive mb-2">
    <table class="table table-bordered table-sm text-center mb-0">
        <thead>
            <tr><th>SMV</th><th>Target / Hr</th><th>Target / Day</th><th>Operators</th><th>Helpers</th><th>Ttl MP</th><th>R-SMV</th><th>Utilization</th><th>Max</th><th>Min</th><th>Bottleneck %</th></tr>
        </thead>
        <tbody>
            <tr class="font-weight-bold">
                <td data-b-out="smv">0</td><td data-b-out="target">0</td><td data-b-out="day">0</td><td data-b-out="operators">0</td><td data-b-out="helpers">0</td>
                <td data-b-out="mp">0</td><td data-b-out="rsmv">0</td><td data-b-out="util">0</td><td data-b-out="max">0</td><td data-b-out="min">0</td><td data-b-out="btl">0</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0">Operations <small class="text-muted">— Section blank = same as the row above · W-Place blank = auto · untick “Do” if not done on this style</small></h6>
    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="bop" title="Add Operation"><i class="fa-solid fa-plus"></i> Add Operation</button>
</div>
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
        <thead>
            <tr><th></th><th>SL</th><th>Section</th><th>From Library</th><th>M/C</th><th>Attachment</th><th>Operation</th><th>SMV</th><th>Tar/Hr</th><th>Req W-Place</th><th>W-Place</th><th>P.Target</th><th>Bottleneck %</th><th>Do</th><th>Remarks</th><th></th></tr>
        </thead>
        <tbody id="bopRowsBody">
            @foreach(empty($rows) ? [['section' => 'Back & Front Part']] : $rows as $index => $op)
                @include('merchandising-sfl::admin.bulletins.partials.operation-row', ['index' => $index, 'op' => $op])
            @endforeach
        </tbody>
    </table>
</div>
<datalist id="bulletinSections">
    @foreach(['Back & Front Part', 'Front Part', 'Back Part', 'Collar', 'Sleeve', 'Lining Part', 'Pocket', 'Assemble', 'Finishing'] as $section)<option value="{{ $section }}">@endforeach
</datalist>
<template id="bopRowTemplate">
    @include('merchandising-sfl::admin.bulletins.partials.operation-row', ['index' => '__INDEX__', 'op' => []])
</template>

@include('merchandising-sfl::admin.partials.line-items-script')
@push('js')
<script>
    (function () {
        const val = (el) => parseFloat(el?.value) || 0;
        const out = (key, text) => { document.querySelector('[data-b-out="' + key + '"]').textContent = text; };

        // Mirrors the Bulletin model — the server recomputes on save.
        function recalc() {
            const target = val(document.querySelector('[data-b-target]'));
            const hours = val(document.querySelector('[data-b-hours]'));
            let smv = 0, mp = 0, helpers = 0, pTargets = [], maxBtl = 0;
            document.querySelectorAll('[data-op-row]').forEach(function (row, i) {
                row.querySelector('[data-op-sl]').textContent = i + 1;
                const active = row.querySelector('[data-op-active]').checked;
                const opSmv = val(row.querySelector('[data-op-smv]'));
                row.classList.toggle('text-muted', ! active);
                const tarHr = opSmv > 0 ? Math.round(60 / opSmv) : 0;
                const req = Math.round(opSmv * target / 60 * 100) / 100;
                const override = parseInt(row.querySelector('[data-op-wp]').value);
                const wp = ! active ? 0 : (override > 0 ? override : Math.max(1, Math.ceil(req)));
                row.querySelector('[data-op-wp]').placeholder = active ? Math.max(1, Math.ceil(req)) : '';
                const pTarget = tarHr * wp;
                row.querySelector('[data-op-tarhr]').textContent = tarHr || '';
                row.querySelector('[data-op-req]').textContent = active && opSmv ? req.toFixed(2) : '';
                row.querySelector('[data-op-ptarget]').textContent = active && pTarget ? pTarget : '';
                const btl = active && wp ? Math.round(req / wp * 100) : 0;
                maxBtl = Math.max(maxBtl, btl);
                const btlCell = row.querySelector('[data-op-btl]');
                btlCell.textContent = btl ? btl + '%' : '';
                btlCell.classList.toggle('text-danger', btl >= 100);
                btlCell.classList.toggle('font-weight-bold', btl >= 100);
                if (! active) return;
                smv += opSmv;
                mp += wp;
                const machine = row.querySelector('[data-op-machine]').selectedOptions[0];
                if (machine && machine.dataset.helper === '1') helpers += wp;
                if (pTarget) pTargets.push(pTarget);
            });
            const rsmv = target > 0 ? mp * 60 / target : 0;
            const min = pTargets.length ? Math.min(...pTargets) : 0;
            out('smv', smv.toFixed(2)); out('target', target); out('day', Math.round(target * hours));
            out('operators', mp - helpers); out('helpers', helpers); out('mp', mp); out('rsmv', rsmv.toFixed(2));
            out('util', rsmv ? Math.round(smv / rsmv * 100) + '%' : '0%');
            out('max', pTargets.length ? Math.max(...pTargets) : 0); out('min', min);
            out('btl', maxBtl + '%');
        }

        // Library pick fills section, name, machine, attachment and SMV.
        $(document).on('change', '[data-op-library]', function () {
            const option = this.selectedOptions[0];
            const row = this.closest('tr');
            if (option && option.value) {
                row.querySelector('[data-op-section]').value = option.dataset.section || '';
                row.querySelector('[data-op-name]').value = option.dataset.name;
                row.querySelector('[data-op-machine]').value = option.dataset.machine || '';
                row.querySelector('[data-op-attachment]').value = option.dataset.attachment || '';
                if (option.dataset.smv) row.querySelector('[data-op-smv]').value = parseFloat(option.dataset.smv);
            }
            recalc();
        });
        $(document).on('change', '[data-bulletin-line]', function () {
            const option = this.selectedOptions[0];
            if (option && option.value) document.querySelector('[data-b-hours]').value = option.dataset.hours;
            recalc();
        });
        $(document).on('change', '[data-bulletin-copy]', function () {
            if (this.value && confirm('Load the operations of this bulletin? Unsaved changes here are lost.')) window.location = this.value;
        });
        document.addEventListener('input', recalc);
        document.addEventListener('change', recalc);
        document.addEventListener('msfl:rows-changed', recalc);
        recalc();
    })();
</script>
@endpush
