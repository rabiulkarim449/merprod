@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Fabric Requisition') }}</title>
@endsection

@php
    $itemOptions = $items->map(fn ($i) => ['id' => $i->id, 'text' => trim(($i->item_code ? $i->item_code . ' — ' : '') . $i->item_name), 'unit' => $i->unit->short_name ?? $i->unit->name ?? '']);
    $rowsOld = old('items', [[]]);
@endphp

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New Fabric Requisition</h4>
            <a href="{{ route('msfl.production.requisitions.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.production.requisitions.store') }}">
                @csrf
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'requisition_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'value' => now()])
                    @include('merchandising-sfl::admin.partials.select', ['name' => 'store_id', 'label' => 'Store', 'required' => true,
                        'options' => $stores->mapWithKeys(fn ($s) => [$s->id => $s->name . ' (' . ($storeTypeLabels[$s->type] ?? $s->type) . ')'])->all(),
                        'value' => $stores->firstWhere('type', $buyerStoreType)->id ?? null])
                    @include('merchandising-sfl::admin.partials.select', ['name' => 'department_id', 'label' => 'Requesting Department', 'required' => true,
                        'options' => $departments->pluck('name', 'id')->all(),
                        'value' => $departments->first(fn ($d) => str_contains(strtolower($d->name), 'cutting'))->id ?? null])
                    @include('merchandising-sfl::admin.partials.select', ['name' => 'requisition_for', 'label' => 'Purpose', 'options' => $purposes->all(), 'value' => $purposes->has('fabrics') ? 'fabrics' : null])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6])
                </div>
                <p class="small text-muted">From the <strong>Buyer Store</strong> the store can only issue fabric received (GRN) for this buyer and style. Picking the style · color fills the items received for it with what is still in the store.</p>
                <div id="reqReceived" class="alert alert-light border small py-2" style="display:none"></div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Items</h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="req" title="Add Item"><i class="fa-solid fa-plus"></i> Add Item</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle" style="max-width:820px">
                        <thead><tr><th>Item (Inventory)</th><th style="width:160px">Qty</th><th style="width:80px">Unit</th><th style="width:40px"></th></tr></thead>
                        <tbody id="reqRowsBody">
                            @foreach($rowsOld as $i => $row)
                                <tr>
                                    <td><select name="items[{{ $i }}][item_id]" class="form-control form-control-sm msfl-select2" data-req-item>
                                        <option value="">— Select item —</option>
                                        @foreach($itemOptions as $o)<option value="{{ $o['id'] }}" data-unit="{{ $o['unit'] }}" @selected(($row['item_id'] ?? null) == $o['id'])>{{ $o['text'] }}</option>@endforeach
                                    </select></td>
                                    <td><input type="number" step="any" min="0" name="items[{{ $i }}][requested_qty]" class="form-control form-control-sm text-right" value="{{ $row['requested_qty'] ?? '' }}"></td>
                                    <td data-req-unit></td>
                                    <td><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="reqRowTemplate">
                    <tr>
                        <td><select name="items[__INDEX__][item_id]" class="form-control form-control-sm msfl-select2" data-req-item>
                            <option value="">— Select item —</option>
                            @foreach($itemOptions as $o)<option value="{{ $o['id'] }}" data-unit="{{ $o['unit'] }}">{{ $o['text'] }}</option>@endforeach
                        </select></td>
                        <td><input type="number" step="any" min="0" name="items[__INDEX__][requested_qty]" class="form-control form-control-sm text-right"></td>
                        <td data-req-unit></td>
                        <td><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
                    </tr>
                </template>

                <button type="submit" class="btn mt-2 btn-sm ui-act ui-act-dispatch" title="Send to Store"><i class="fa-solid fa-truck-fast"></i> Send to Store</button>
                <a href="{{ route('msfl.production.requisitions.index') }}" class="btn mt-2 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
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
    function unit(sel) { sel.closest('tr').querySelector('[data-req-unit]').textContent = sel.selectedOptions[0]?.dataset.unit || ''; }
    if (typeof $ !== 'undefined') { $(document).on('change', '[data-req-item]', function () { unit(this); }); }
    document.querySelectorAll('[data-req-item]').forEach(unit);

    // PO picked → rows for the items the Buyer Store received for its style, qty = what is left.
    const received = @json($received);
    const names = @json($itemOptions->pluck('text', 'id'));
    const body = document.getElementById('reqRowsBody');
    const box = document.getElementById('reqReceived');
    function fillItems() {
        const rows = (received[document.getElementById('poSelect').value] || []);
        box.style.display = rows.length ? '' : 'none';
        box.innerHTML = rows.length ? 'Received for this style: ' + rows.map(r => (names[r.item_id] || r.item_id) + ' — received ' + r.received + ', issued ' + r.issued + ', <strong>left ' + r.balance + '</strong>').join(' · ') : '';
        // Only when the rows are still untouched (empty, or filled by an earlier pick).
        const touched = Array.from(body.querySelectorAll('tr')).some(tr => tr.querySelector('[data-req-item]').value && ! tr.dataset.autofilled);
        if (touched || ! rows.some(r => r.balance > 0)) return;
        body.querySelectorAll('tr').forEach((tr, i) => { if (i > 0) tr.remove(); });
        rows.filter(r => r.balance > 0).forEach(function (r, i) {
            if (i > 0) document.querySelector('[data-line-items-add="req"]').click();
            const tr = body.querySelector('tr:last-child');
            tr.dataset.autofilled = '1';
            $(tr.querySelector('[data-req-item]')).val(String(r.item_id)).trigger('change');
            tr.querySelector('[name$="[requested_qty]"]').value = r.balance;
        });
    }
    $('#poSelect').on('change', fillItems);
    if (! @json((bool) old('items'))) fillItems();
})();
</script>
@endpush
