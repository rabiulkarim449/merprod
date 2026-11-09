@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('New Cutting') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">New Cutting</h4>
            <a href="{{ route('msfl.production.cuttings.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('msfl.production.cuttings.store') }}">
                @csrf
                <div class="row">
                    @include('merchandising-sfl::admin.production.partials.po-select', ['selected' => $selectedPo])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'cutting_date', 'label' => 'Cutting Date', 'type' => 'date', 'required' => true, 'value' => now()])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'table_no', 'label' => 'Table No'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'lay_count', 'label' => 'Lay / Ply Count', 'type' => 'number'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'fabric_used', 'label' => 'Fabric Used', 'type' => 'number'])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'bundle_size', 'label' => 'Pcs per Bundle', 'type' => 'number', 'required' => true, 'value' => 20])
                    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks'])
                </div>

                <h6>Cut Quantity per Size <small class="text-muted">— total: <span id="cutTotal">0</span> pcs</small></h6>
                <div id="sizeHint" class="small text-muted mb-2">Select a PO to see its order and already-cut quantity per size.</div>
                <div class="row">
                    @foreach($sizes as $size)
                        <div class="col-md-2 col-4 mb-2" data-size-box="{{ $size->id }}">
                            <label class="form-label small mb-0">{{ $size->name }} <small class="text-muted" data-size-info="{{ $size->id }}"></small></label>
                            <input type="number" min="0" step="1" name="sizes[{{ $size->id }}]" class="form-control form-control-sm" value="{{ old('sizes.' . $size->id) }}" data-cut-qty>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <h6 class="mb-0">Parts Cut — Color <span id="partColor" class="badge badge-light border">select PO</span> × Size <small class="text-muted">(part from Master Data → Garment Parts; picking a part fills the cut qty — change it if a part has 2 per piece, e.g. Sleeve)</small></h6>
                    <button type="button" class="btn btn-sm ui-act ui-act-create" data-line-items-add="part" title="Add Part"><i class="fa-solid fa-plus"></i> Add Part</button>
                </div>
                @error('parts')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th style="min-width:150px">Part</th>@foreach($sizes as $size)<th class="text-center" data-size-col="{{ $size->id }}" style="width:90px">{{ $size->name }}</th>@endforeach<th class="text-right" style="width:80px">Total</th><th style="width:40px"></th></tr></thead>
                        <tbody id="partRowsBody">
                            @foreach(old('parts', [[]]) as $i => $part)
                                <tr>
                                    <td><select name="parts[{{ $i }}][part_name]" class="form-control form-control-sm" data-part-name><option value="">— Part —</option>@foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(($part['part_name'] ?? '') === $gp)>{{ $gp }}</option>@endforeach</select></td>
                                    @foreach($sizes as $size)
                                        <td data-size-col="{{ $size->id }}"><input type="number" min="0" step="1" name="parts[{{ $i }}][sizes][{{ $size->id }}]" class="form-control form-control-sm text-right" value="{{ $part['sizes'][$size->id] ?? '' }}" data-part-size="{{ $size->id }}"></td>
                                    @endforeach
                                    <td class="text-right" data-part-total>0</td>
                                    <td><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template id="partRowTemplate">
                    <tr>
                        <td><select name="parts[__INDEX__][part_name]" class="form-control form-control-sm" data-part-name><option value="">— Part —</option>@foreach($garmentParts as $gp)<option value="{{ $gp }}" @selected(('') === $gp)>{{ $gp }}</option>@endforeach</select></td>
                        @foreach($sizes as $size)
                            <td data-size-col="{{ $size->id }}"><input type="number" min="0" step="1" name="parts[__INDEX__][sizes][{{ $size->id }}]" class="form-control form-control-sm text-right" data-part-size="{{ $size->id }}"></td>
                        @endforeach
                        <td class="text-right" data-part-total>0</td>
                        <td><button type="button" class="btn-custom ui-act ui-act-delete" data-line-items-remove title="Delete"><i class="fa-solid fa-trash"></i></button></td>
                    </tr>
                </template>

                <button type="submit" class="btn mt-2 btn-sm ui-act ui-act-save" title="Save Cutting & Make Bundles"><i class="fa-solid fa-floppy-disk"></i> Save Cutting &amp; Make Bundles</button>
                <a href="{{ route('msfl.production.cuttings.index') }}" class="btn mt-2 btn-sm ui-act ui-act-cancel" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</a>
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
    // PO size order qty and what is already cut, so the cutter sees the balance.
    const orderQty = @json($pos->mapWithKeys(fn ($po) => [$po->id => $po->sizes->pluck('qty', 'size_id')]));
    const cutQty = @json($cutBySize);
    const select = document.getElementById('poSelect');
    function total() {
        let t = 0;
        document.querySelectorAll('[data-cut-qty]').forEach(i => t += parseInt(i.value || 0, 10));
        document.getElementById('cutTotal').textContent = t;
    }
    function hint() {
        const po = select.value, ordered = orderQty[po] || {}, cut = cutQty[po] || {};
        document.querySelectorAll('[data-size-info]').forEach(el => {
            const id = el.dataset.sizeInfo, o = ordered[id];
            el.textContent = o ? '(order ' + o + ', cut ' + (cut[id] || 0) + ')' : '';
        });
        document.getElementById('sizeHint').style.display = po ? 'none' : '';
    }
    // PO picked → each empty size box gets what is still to cut (order − already cut); change it if the lay is smaller.
    function fillRemaining() {
        const po = select.value, ordered = orderQty[po] || {}, cut = cutQty[po] || {};
        document.querySelectorAll('[data-cut-qty]').forEach(i => {
            const id = i.name.match(/sizes\[(\d+)\]/)[1];
            const left = Math.max(0, (ordered[id] || 0) - (cut[id] || 0));
            if (i.value === '' || i.dataset.autofilled === i.value) { i.value = left || ''; i.dataset.autofilled = i.value; }
        });
        total();
    }
    // Parts per size: only the PO's sizes, row totals, and a new part starts from the cut qty.
    const poColor = @json($pos->mapWithKeys(fn ($po) => [$po->id => $po->color->name ?? '']));
    function partColumns() {
        document.getElementById('partColor').textContent = poColor[select.value] || 'select PO';
        const ordered = orderQty[select.value] || null;
        document.querySelectorAll('[data-size-col]').forEach(el => el.style.display = ! ordered || ordered[el.dataset.sizeCol] ? '' : 'none');
        document.querySelectorAll('[data-size-box]').forEach(el => el.style.display = ! ordered || ordered[el.dataset.sizeBox] ? '' : 'none');
    }
    function partTotals() {
        document.querySelectorAll('#partRowsBody tr').forEach(row => {
            let t = 0;
            row.querySelectorAll('[data-part-size]').forEach(i => t += parseInt(i.value || 0, 10));
            row.querySelector('[data-part-total]').textContent = t;
        });
    }
    document.addEventListener('change', e => {
        if (! e.target.matches('[data-part-name]') || ! e.target.value) return;
        const row = e.target.closest('tr');
        row.querySelectorAll('[data-part-size]').forEach(i => {
            if (i.value === '') i.value = document.querySelector('[name="sizes[' + i.dataset.partSize + ']"]').value;
        });
        partTotals();
    });
    document.getElementById('partRowsBody').addEventListener('msfl:rows-changed', () => { partColumns(); partTotals(); });
    document.addEventListener('input', e => { if (e.target.matches('[data-cut-qty]')) total(); if (e.target.matches('[data-part-size]')) partTotals(); });
    if (typeof $ !== 'undefined') { $(select).on('change', () => { hint(); partColumns(); fillRemaining(); }); } else { select.addEventListener('change', () => { hint(); partColumns(); fillRemaining(); }); }
    hint(); total(); partColumns(); partTotals();
})();
</script>
@endpush
