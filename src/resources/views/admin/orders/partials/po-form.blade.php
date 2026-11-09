{{--
    PO lines inside the order form (pos[] — saved with the order). One row = PO + style + color with its size breakdown.
    props: order, formSizes, selectedSizeIds, styles, colors, shipModes, styleFill (style_id => [unit_price, shipment_date])
--}}
@php
    $rows = old('pos', $order->exists
        ? $order->pos->map(fn ($po) => $po->only(['id', 'po_no', 'style_id', 'color_id', 'unit_price', 'ship_mode_id', 'remarks', 'needs_embroidery', 'needs_washing', 'applique_ih', 'studs_stones_ih', 'heat_seal_ih'])
            + ['pcd_date' => $po->pcd_date?->toDateString(), 'shipment_date' => $po->shipment_date?->toDateString(), 'sizes' => $po->sizes->pluck('qty', 'size_id')->all()])->all()
        : []);
    if (! $rows) {
        $rows = [[]];
    }
    $rowProps = compact('formSizes', 'styles', 'colors', 'shipModes');
@endphp
<hr>
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
    <h6 class="mb-0">PO Lines <small class="text-muted">— one row per PO · style (a PO can have several styles); qty by size</small></h6>
    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="po" title="Add Row"><i class="fa-solid fa-plus"></i> Add Row</button>
</div>
@error('pos')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
<div class="row align-items-end">
    <div class="col-md-6 mb-2">
        <label class="form-label">Sizes of this order <span class="text-danger">*</span></label>
        <select name="size_ids[]" multiple class="form-control form-control-sm msfl-select2{{ $errors->has('size_ids') ? ' is-invalid' : '' }}" data-po-sizes data-placeholder="Pick the sizes (XS, S, M …)">
            @foreach($formSizes as $size)
                <option value="{{ $size->id }}" @selected(in_array($size->id, $selectedSizeIds, true))>{{ $size->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-2 small text-muted">Only these sizes get a column below. A style without one of them: leave that cell empty.</div>
</div>
@if($formSizes->isEmpty())
    <div class="alert alert-warning">No active sizes — add them in Inventory → Sizes.</div>
@endif
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-1 po-form-table">
        <thead>
            <tr>
                <th>#</th><th>PO No <span class="text-danger">*</span></th><th>Style <span class="text-danger">*</span></th><th>Color <span class="text-danger">*</span></th>
                @foreach($formSizes as $size)<th class="text-center" data-size-col="{{ $size->id }}">{{ $size->name }}</th>@endforeach
                <th class="text-center text-muted small" data-no-size>Pick sizes ↑</th>
                <th class="text-right">Qty</th><th>Unit Price <span class="text-danger">*</span></th><th class="text-right">Value</th>
                <th>PCD</th><th>Shipment</th><th>Ship Mode</th><th>Production</th><th>Remarks</th><th></th>
            </tr>
        </thead>
        <tbody id="poRowsBody">
            @foreach($rows as $i => $row)
                @include('merchandising-sfl::admin.orders.partials.po-row', ['i' => $i, 'row' => $row] + $rowProps)
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="4" class="text-right">Total</td>
                @foreach($formSizes as $size)<td class="text-right" data-po-size-total="{{ $loop->index }}" data-size-col="{{ $size->id }}">0</td>@endforeach
                <td data-no-size></td>
                <td class="text-right" data-po-total-qty>0</td><td></td><td class="text-right" data-po-total-value>0.00</td><td colspan="6"></td>
            </tr>
        </tfoot>
    </table>
</div>
<p class="small text-muted">
    Style list shows the chosen buyer's styles. Picking a style sets its <strong>Color</strong> (Master Data → Styles) and fills Unit Price (cost sheet) and Shipment date when empty.
    <i class="fa-solid fa-copy"></i> copies a row — e.g. the same PO with another style. Empty rows are ignored.
    Production: Emb / Wash = the PO goes through embroidery / washing.
</p>

<template id="poRowTemplate">
    @include('merchandising-sfl::admin.orders.partials.po-row', ['i' => '__INDEX__', 'row' => []] + $rowProps)
</template>

@include('merchandising-sfl::admin.partials.line-items-script')
@push('js')
<script>
(function () {
    const body = document.getElementById('poRowsBody');
    const styleFill = @json($styleFill);
    const buyerSelect = () => document.querySelector('[name="buyer_id"]');

    function recalc() {
        const sizeTotals = [];
        let qty = 0, value = 0;
        body.querySelectorAll('tr[data-po-row]').forEach(function (row, r) {
            row.querySelector('[data-po-sl]').textContent = r + 1;
            let rowQty = 0;
            row.querySelectorAll('[data-po-size]').forEach(function (el, s) {
                const v = parseInt(el.value) || 0;
                rowQty += v;
                sizeTotals[s] = (sizeTotals[s] || 0) + v;
            });
            const rowValue = rowQty * (parseFloat(row.querySelector('[data-po-price]').value) || 0);
            row.querySelector('[data-po-qty]').textContent = rowQty.toLocaleString();
            row.querySelector('[data-po-value]').textContent = rowValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            qty += rowQty;
            value += rowValue;
        });
        document.querySelectorAll('[data-po-size-total]').forEach(el => el.textContent = (sizeTotals[el.dataset.poSizeTotal] || 0).toLocaleString());
        document.querySelector('[data-po-total-qty]').textContent = qty.toLocaleString();
        document.querySelector('[data-po-total-value]').textContent = value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Only the order buyer's styles can be picked.
    function filterStyles(scope) {
        const buyer = buyerSelect()?.value || '';
        (scope || body).querySelectorAll('[data-po-style] option[data-buyer]').forEach(function (o) {
            o.disabled = buyer !== '' && o.dataset.buyer !== buyer;
            o.hidden = o.disabled;
        });
    }

    // Size columns: only the picked sizes; un-picking a size clears its cells.
    function applySizes() {
        const picked = ($('[data-po-sizes]').val() || []).map(String);
        const table = body.closest('table');
        let cleared = false;
        table.querySelectorAll('[data-size-col]').forEach(function (cell) {
            const on = picked.includes(cell.dataset.sizeCol);
            cell.classList.toggle('d-none', ! on);
            const input = cell.querySelector('input');
            if (! on && input && input.value !== '') { input.value = ''; cleared = true; }
        });
        table.querySelectorAll('[data-no-size]').forEach(cell => cell.classList.toggle('d-none', picked.length > 0));
        if (cleared) recalc();
    }

    // The style's color goes to the row and is locked; a style without a color lets you pick one.
    function lockColor(row) {
        const style = row.querySelector('[data-po-style]');
        const color = row.querySelector('[data-po-color]');
        const styleColor = style?.selectedOptions[0]?.dataset.color || '';
        if (! color) return;
        if (styleColor) color.value = styleColor;
        color.disabled = styleColor !== '';
        $(color).trigger('change.select2');
    }

    function fillFromStyle(select) {
        const fill = styleFill[select.value];
        const row = select.closest('tr');
        if (row) lockColor(row);
        if (! fill || ! row) return;
        const price = row.querySelector('[data-po-price]');
        const ship = row.querySelector('[data-po-ship]');
        if (price && price.value === '' && fill.unit_price != null) price.value = fill.unit_price;
        if (ship && ship.value === '' && fill.shipment_date) ship.value = fill.shipment_date;
        recalc();
    }

    // Copy a row: same values (sizes too) without the saved id, right below it.
    $(document).on('click', '[data-po-copy]', function () {
        const src = this.closest('tr');
        document.querySelector('[data-line-items-add="po"]').click();
        const copy = body.lastElementChild;
        src.querySelectorAll('input, select').forEach(function (el) {
            const field = el.name.replace(/^pos\[[^\]]+\]/, '');
            if (field === '[id]') return;
            const target = copy.querySelector('[name$="' + field.replace(/"/g, '') + '"]');
            if (! target) return;
            if (el.type === 'checkbox') target.checked = el.checked; else target.value = el.value;
            if (target.tagName === 'SELECT') $(target).trigger('change.select2');
        });
        src.after(copy);
        lockColor(copy);
        recalc();
    });

    body.addEventListener('input', recalc);
    body.addEventListener('msfl:rows-changed', function (e) {
        if (e.detail?.row) { filterStyles(e.detail.row); lockColor(e.detail.row); }
        applySizes();
        recalc();
    });
    $(document).on('change', '[data-po-sizes]', function () { applySizes(); });
    $(document).on('change', '[data-po-style]', function () { fillFromStyle(this); });
    $(document).on('change', '[name="buyer_id"]', function () { filterStyles(); });
    $(function () {
        filterStyles();
        body.querySelectorAll('tr[data-po-row]').forEach(lockColor);
        applySizes();
        recalc();
    });
})();
</script>
@endpush
