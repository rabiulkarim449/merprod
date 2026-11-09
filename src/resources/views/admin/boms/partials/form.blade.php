{{-- props: bom (model, may be unsaved), styles, orders, itemOptions, colors, sizes, uoms, suppliers --}}
@php
    $lines = old('items', $bom->relationLoaded('items') ? $bom->items->toArray() : []);
    $bomType = old('bom_type', $bom->bom_type);
@endphp
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'style_id', 'label' => 'Style', 'required' => true, 'col' => 4, 'options' => $styles->mapWithKeys(fn ($s) => [$s->id => $s->label()]), 'value' => $bom->style_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'order_id', 'label' => 'Order (for Order BOM)', 'col' => 4, 'placeholder' => '— None (per piece only) —', 'options' => $orders->mapWithKeys(fn ($o) => [$o->id => $o->order_no . ($o->buyer_order_ref ? ' — ' . $o->buyer_order_ref : '')]), 'value' => $bom->order_id])
    <div class="col-md-4 mb-3">
        <label class="form-label">BOM Type <span class="text-danger">*</span></label>
        <div>
            @foreach(\ME\MerchandisingSfl\Models\Bom::TYPES as $value => $label)
                <div class="custom-control custom-radio custom-control-inline">
                    <input type="radio" name="bom_type" value="{{ $value }}" id="bomType{{ $value }}" class="custom-control-input" @checked($bomType === $value)>
                    <label class="custom-control-label" for="bomType{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-md-4 mb-3" data-bom-file-section>
        <label class="form-label">Buyer BOM File (PDF / Excel)</label>
        <input type="file" name="bom_file" class="form-control form-control-sm">
        @if($bom->bom_file)
            <small><a href="{{ $bom->fileUrl() }}" target="_blank">Current file</a> — upload to replace</small>
        @endif
    </div>
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'col' => 8, 'value' => $bom->remarks])
</div>

<div data-bom-items-section>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="mb-0">Items <small class="text-muted">(consumption per piece; blank color / size = applies to all)</small></h6>
        <div>
            <button type="button" class="btn btn-sm ui-act ui-act-import" id="bomFromCostSheet" style="display:none" title="Fill from Cost Sheet"><i class="fa-solid fa-file-import"></i> Fill from Cost Sheet <span></span></button>
            <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="bom" title="Add Item"><i class="fa-solid fa-plus"></i> Add Item</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead><tr><th>Item</th><th>Color</th><th>Size</th><th>Placement</th><th>Cons / pc</th><th>Unit</th><th>Wastage %</th><th>Rate</th><th>Supplier</th><th>Remarks</th><th></th></tr></thead>
            <tbody id="bomRowsBody">
                @foreach(empty($lines) ? [[]] : $lines as $index => $line)
                    @include('merchandising-sfl::admin.boms.partials.line-row', ['index' => $index, 'line' => $line])
                @endforeach
            </tbody>
        </table>
    </div>
    <template id="bomRowTemplate">
        @include('merchandising-sfl::admin.boms.partials.line-row', ['index' => '__INDEX__', 'line' => []])
    </template>
</div>

@include('merchandising-sfl::admin.partials.line-items-script')
@push('js')
<script>
    (function () {
        function toggleType() {
            const type = document.querySelector('input[name="bom_type"]:checked')?.value;
            document.querySelector('[data-bom-file-section]').style.display = type === 'file' ? '' : 'none';
            document.querySelector('[data-bom-items-section]').style.display = type === 'manual' ? '' : 'none';
        }
        document.querySelectorAll('input[name="bom_type"]').forEach(el => el.addEventListener('change', toggleType));
        toggleType();

        // Picking an item fills its default unit, supplier and rate.
        $(document).on('change', '[data-bom-item]', function () {
            const option = this.selectedOptions[0];
            const row = this.closest('tr');
            if (! option) return;
            if (option.dataset.uom) row.querySelector('[data-bom-uom]').value = option.dataset.uom;
            if (option.dataset.supplier) row.querySelector('[data-bom-supplier]').value = option.dataset.supplier;
            if (option.dataset.rate && ! row.querySelector('[data-bom-rate]').value) row.querySelector('[data-bom-rate]').value = option.dataset.rate;
        });
    })();
</script>
@endpush

{{-- Style picked → its order; its cost sheet's fabric / trims lines can fill the items (per dozen ÷ 12 = per piece). --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'style_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::styles(), 'fields' => ['order_id' => 'order_id']])
@push('js')
<script>
(function () {
    const fromCs = @json(\ME\MerchandisingSfl\Support\Autofill::bomLinesFromCostSheet());
    const style = document.querySelector('[name="style_id"]');
    const btn = document.getElementById('bomFromCostSheet');
    const body = document.getElementById('bomRowsBody');

    function toggle() {
        const cs = fromCs[style.value];
        btn.style.display = cs && cs.lines.length ? '' : 'none';
        if (cs) btn.querySelector('span').textContent = '(' + cs.cost_sheet + ', ' + cs.lines.length + ' items)';
    }
    function set(row, field, value) {
        const el = row.querySelector('[name$="[' + field + ']"]');
        if (! el || value === null || value === undefined) return;
        el.value = String(value);
        if (el.tagName === 'SELECT') $(el).trigger('change.select2');
    }
    btn.addEventListener('click', function () {
        const cs = fromCs[style.value];
        if (! cs) return;
        // Drop empty rows, then one row per cost sheet line.
        body.querySelectorAll('tr').forEach(r => { if (! r.querySelector('[data-bom-item]').value && body.querySelectorAll('tr').length > 1) r.remove(); });
        cs.lines.forEach(function (line, i) {
            let row = body.querySelector('tr:last-child');
            if (i > 0 || row.querySelector('[data-bom-item]').value) {
                document.querySelector('[data-line-items-add="bom"]').click();
                row = body.querySelector('tr:last-child');
            }
            $(row.querySelector('[data-bom-item]')).val(String(line.item_id)).trigger('change');
            ['consumption', 'uom_id', 'rate', 'supplier_id'].forEach(f => set(row, f, line[f]));
        });
    });
    $(style).on('change', toggle);
    toggle();
})();
</script>
@endpush
