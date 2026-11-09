{{--
    Commercial invoice: shipping fields + carton packing list.
    props: invoice, lc (active ExportLc), avail (po_id => [po, sizes, packed, invoiced, free]), sizes (union, XS → 3XL), cartonRows, shipModes
--}}
@php $cur = $lc->currency->code ?? ''; @endphp
<input type="hidden" name="export_lc_id" value="{{ $lc->id }}">
<div class="row">
    @include('merchandising-sfl::admin.partials.input', ['name' => 'invoice_date', 'label' => 'Invoice Date', 'type' => 'date', 'required' => true, 'value' => $invoice->invoice_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'exp_no', 'label' => 'EXP No', 'value' => $invoice->exp_no])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'exp_date', 'label' => 'EXP Date', 'type' => 'date', 'value' => $invoice->exp_date])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'ship_mode_id', 'label' => 'Ship Mode', 'options' => $shipModes->pluck('name', 'id'), 'value' => $invoice->ship_mode_id])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'bl_no', 'label' => 'B/L / AWB No', 'value' => $invoice->bl_no])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'bl_date', 'label' => 'B/L / AWB Date', 'type' => 'date', 'value' => $invoice->bl_date])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'vessel', 'label' => 'Vessel / Flight', 'value' => $invoice->vessel])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'container_no', 'label' => 'Container No', 'value' => $invoice->container_no])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'port_of_loading', 'label' => 'Port of Loading', 'value' => $invoice->port_of_loading])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'port_of_discharge', 'label' => 'Port of Discharge', 'value' => $invoice->port_of_discharge])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'final_destination', 'label' => 'Final Destination', 'value' => $invoice->final_destination])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'value' => $invoice->remarks])
</div>

<h6 class="mt-2">Can ship <small class="text-muted">— per size: packed in production − already on other invoices</small></h6>
<div class="table-responsive">
    <table class="table table-bordered table-sm mb-3">
        <thead><tr><th>PO · Style · Color</th>@foreach($sizes as $s)<th class="text-center">{{ $s->name }}</th>@endforeach<th class="text-right">Total</th><th class="text-right">FOB</th></tr></thead>
        <tbody>
            @forelse($avail as $poId => $a)
                <tr>
                    <td class="text-nowrap">{{ $a['po']->po_no }} · {{ $a['po']->style->style_no ?? '' }} · {{ $a['po']->color->name ?? '' }}</td>
                    @foreach($sizes as $s)
                        <td class="text-center" data-free="{{ $poId }}-{{ $s->id }}" data-free-qty="{{ $a['free'][$s->id] ?? 0 }}">@if($a['sizes']->contains('id', $s->id)){{ $a['free'][$s->id] ?? 0 }}<br><small class="text-muted">of {{ $a['packed'][$s->id] ?? 0 }}</small>@else<span class="text-muted">–</span>@endif</td>
                    @endforeach
                    <td class="text-right font-weight-bold">{{ array_sum($a['free']) }}</td>
                    <td class="text-right">{{ number_format((float) $a['po']->unit_price, 4) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $sizes->count() + 3 }}" class="text-center text-muted">This LC has no PO.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
    <h6 class="mb-0">Carton Packing List <small class="text-muted">— one row = cartons From…To of one PO packed alike: pcs per size in <strong>each</strong> carton, weight per carton (kg), carton size (cm)</small></h6>
    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="ctn" title="Add Row"><i class="fa-solid fa-plus"></i> Add Row</button>
</div>
@error('cartons')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-1">
        <thead>
            <tr>
                <th>PO · Style · Color</th><th>Ctn From</th><th>Ctn To</th><th class="text-right">Ctns</th>
                @foreach($sizes as $s)<th class="text-center">{{ $s->name }}</th>@endforeach
                <th class="text-right">Pcs / Ctn</th><th class="text-right">Total Pcs</th><th>NW / Ctn</th><th>GW / Ctn</th><th>L</th><th>W</th><th>H</th><th></th>
            </tr>
        </thead>
        <tbody id="ctnRowsBody">
            @foreach($cartonRows as $i => $row)
                @include('merchandising-sfl::admin.commercial.invoices.partials.carton-row', ['i' => $i, 'row' => $row])
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="3" class="text-right">Total</td><td class="text-right" data-ctn-total-count>0</td>
                @foreach($sizes as $s)<td class="text-center" data-ctn-total-size="{{ $s->id }}">0</td>@endforeach
                <td></td><td class="text-right" data-ctn-total-pcs>0</td>
                <td colspan="7" class="small">NW <span data-ctn-total-nw>0</span> kg · GW <span data-ctn-total-gw>0</span> kg · CBM <span data-ctn-total-cbm>0</span> · Value {{ $cur }} <span data-ctn-total-value>0.00</span></td>
            </tr>
        </tfoot>
    </table>
</div>
<p class="small text-muted">A size not in the row's PO is locked. Red = more than can be shipped for that PO and size (counting every row). Invoice qty, cartons, weights and CBM are worked out from these rows.</p>

<template id="ctnRowTemplate">
    @include('merchandising-sfl::admin.commercial.invoices.partials.carton-row', ['i' => '__INDEX__', 'row' => []])
</template>

@include('merchandising-sfl::admin.partials.line-items-script')
@push('js')
<script>
(function () {
    const body = document.getElementById('ctnRowsBody');
    const prices = @json($avail->map(fn ($a) => (float) $a['po']->unit_price));
    const num = (el) => parseFloat(el?.value) || 0;
    const fmt = (v, d = 2) => v.toLocaleString(undefined, { minimumFractionDigits: d, maximumFractionDigits: d });

    // A row's sizes: only those of its PO are open.
    function lockSizes(row) {
        const opt = row.querySelector('[data-ctn-po]').selectedOptions[0];
        const allowed = (opt?.dataset.sizes || '').split(',').filter(Boolean);
        row.querySelectorAll('[data-ctn-size]').forEach(function (input) {
            const on = allowed.includes(input.dataset.ctnSize);
            input.disabled = ! on;
            if (! on) input.value = '';
        });
    }

    function recalc() {
        const sizeTotals = {}, used = {};
        let count = 0, pcs = 0, nw = 0, gw = 0, cbm = 0, value = 0;
        body.querySelectorAll('tr[data-ctn-row]').forEach(function (row) {
            const po = row.querySelector('[data-ctn-po]').value;
            const n = Math.max(0, num(row.querySelector('[data-ctn-to]')) - num(row.querySelector('[data-ctn-from]')) + 1);
            let per = 0;
            row.querySelectorAll('[data-ctn-size]').forEach(function (input) {
                const q = parseInt(input.value) || 0;
                per += q;
                sizeTotals[input.dataset.ctnSize] = (sizeTotals[input.dataset.ctnSize] || 0) + q * n;
                if (po) used[po + '-' + input.dataset.ctnSize] = (used[po + '-' + input.dataset.ctnSize] || 0) + q * n;
            });
            row.querySelector('[data-ctn-count]').textContent = n;
            row.querySelector('[data-ctn-per]').textContent = per;
            row.querySelector('[data-ctn-pcs]').textContent = (per * n).toLocaleString();
            count += n; pcs += per * n;
            nw += num(row.querySelector('[data-ctn-net_weight]')) * n;
            gw += num(row.querySelector('[data-ctn-gross_weight]')) * n;
            cbm += num(row.querySelector('[data-ctn-length]')) * num(row.querySelector('[data-ctn-width]')) * num(row.querySelector('[data-ctn-height]')) / 1000000 * n;
            value += per * n * (prices[po] || 0);
        });
        document.querySelector('[data-ctn-total-count]').textContent = count;
        document.querySelectorAll('[data-ctn-total-size]').forEach(el => el.textContent = (sizeTotals[el.dataset.ctnTotalSize] || 0).toLocaleString());
        document.querySelector('[data-ctn-total-pcs]').textContent = pcs.toLocaleString();
        document.querySelector('[data-ctn-total-nw]').textContent = fmt(nw);
        document.querySelector('[data-ctn-total-gw]').textContent = fmt(gw);
        document.querySelector('[data-ctn-total-cbm]').textContent = fmt(cbm, 3);
        document.querySelector('[data-ctn-total-value]').textContent = fmt(value);
        // Over what can be shipped → mark the "can ship" cell.
        document.querySelectorAll('[data-free]').forEach(function (cell) {
            cell.classList.toggle('table-danger', (used[cell.dataset.free] || 0) > parseInt(cell.dataset.freeQty));
        });
    }

    function lastTo() {
        let max = 0;
        body.querySelectorAll('[data-ctn-to]').forEach(el => max = Math.max(max, parseInt(el.value) || 0));
        return max;
    }

    // New row: cartons continue after the last one.
    body.addEventListener('msfl:rows-changed', function (e) {
        const row = e.detail?.row;
        if (row && ! row.querySelector('[data-ctn-from]').value) {
            row.querySelector('[data-ctn-from]').value = lastTo() + 1;
        }
        if (row) lockSizes(row);
        recalc();
    });

    // Copy: same PO, pcs, weights and size for the next cartons.
    $(document).on('click', '[data-ctn-copy]', function () {
        const src = this.closest('tr');
        const from = num(src.querySelector('[data-ctn-from]')), to = num(src.querySelector('[data-ctn-to]'));
        document.querySelector('[data-line-items-add="ctn"]').click();
        const copy = body.lastElementChild;
        src.querySelectorAll('input, select').forEach(function (el) {
            const field = el.name.replace(/^cartons\[[^\]]+\]/, '');
            const target = copy.querySelector('[name$="' + field + '"]');
            if (target) target.value = el.value;
        });
        const span = Math.max(1, to - from + 1), start = lastTo() + 1;
        copy.querySelector('[data-ctn-from]').value = start;
        copy.querySelector('[data-ctn-to]').value = start + span - 1;
        lockSizes(copy);
        recalc();
    });

    body.addEventListener('input', recalc);
    body.addEventListener('change', function (e) { if (e.target.matches('[data-ctn-po]')) { lockSizes(e.target.closest('tr')); recalc(); } });
    body.querySelectorAll('tr[data-ctn-row]').forEach(function (row) {
        if (! row.querySelector('[data-ctn-from]').value) row.querySelector('[data-ctn-from]').value = 1;
        lockSizes(row);
    });
    recalc();
})();
</script>
@endpush
