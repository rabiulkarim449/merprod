{{--
    Export LC / Sales Contract header + its POs (po_ids[]) on one page.
    props: lc (model, may be unsaved), buyers, currencies, paymentTerms, banks, pos (selectable POs of every buyer), pickedPoIds
--}}
@php
    $val = fn ($f, $d = null) => old($f, $lc->{$f} ?? $d);
    $date = fn ($f) => ($v = $val($f)) instanceof \Carbon\CarbonInterface ? $v->format('Y-m-d') : $v;
@endphp
@if($banks->isEmpty() || $paymentTerms->isEmpty())
    <div class="alert alert-warning py-2 small">Set up Commercial → Setup → <a href="{{ route('msfl.masters.index', 'banks') }}">Banks</a> / <a href="{{ route('msfl.masters.index', 'payment-terms') }}">Payment Terms</a> first — the LC picks its banks and term from there.</div>
@endif
<div class="row">
    @include('merchandising-sfl::admin.partials.select', ['name' => 'buyer_id', 'label' => 'Buyer', 'required' => true, 'options' => $buyers->pluck('name', 'id'), 'value' => $lc->buyer_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'type', 'label' => 'Type', 'required' => true, 'options' => \ME\MerchandisingSfl\Models\Commercial\ExportLc::TYPES, 'value' => $lc->type, 'placeholder' => '—'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'buyer_lc_no', 'label' => "Buyer's LC / SC No", 'required' => true, 'value' => $lc->buyer_lc_no])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'lc_date', 'label' => 'LC / SC Date', 'type' => 'date', 'required' => true, 'value' => $lc->lc_date])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'currency_id', 'label' => 'Currency', 'options' => $currencies->pluck('code', 'id'), 'value' => $lc->currency_id])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'lc_value', 'label' => 'LC Value', 'type' => 'number', 'required' => true, 'value' => $lc->lc_value, 'attrs' => 'step=any data-lc-value'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'tolerance_percent', 'label' => 'Tolerance ± %', 'type' => 'number', 'value' => $lc->tolerance_percent, 'attrs' => 'step=any'])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'last_shipment_date', 'label' => 'Last Shipment Date', 'type' => 'date', 'value' => $lc->last_shipment_date])

    @include('merchandising-sfl::admin.partials.input', ['name' => 'expiry_date', 'label' => 'Expiry Date', 'type' => 'date', 'value' => $lc->expiry_date])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'payment_term_id', 'label' => 'Payment Term', 'required' => true, 'options' => $paymentTerms->pluck('name', 'id'), 'value' => $lc->payment_term_id])
    @include('merchandising-sfl::admin.partials.select', ['name' => 'issuing_bank_id', 'label' => "Issuing Bank (buyer's)", 'options' => $banks->whereIn('bank_type', ['buyer', 'other'])->mapWithKeys(fn ($b) => [$b->id => $b->label()]), 'value' => $lc->issuing_bank_id])

    @include('merchandising-sfl::admin.partials.select', ['name' => 'lien_bank_id', 'label' => 'Lien / Advising Bank (ours)', 'options' => $banks->where('bank_type', 'our')->mapWithKeys(fn ($b) => [$b->id => $b->label()]), 'value' => $lc->lien_bank_id])
    @include('merchandising-sfl::admin.commercial.partials.attachments-input', ['name' => 'attachment', 'label' => 'LC copy / documents', 'value' => $lc->attachment, 'col' => 6])
    @include('merchandising-sfl::admin.partials.input', ['name' => 'remarks', 'label' => 'Remarks', 'col' => 6, 'value' => $lc->remarks])
    @if($lc->status === 'active')
        @include('merchandising-sfl::admin.partials.input', ['name' => 'amendment_remarks', 'label' => 'Amendment note (if value / dates change)', 'col' => 6, 'value' => null])
    @endif
</div>

<hr>
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
    <h6 class="mb-0">POs on this LC <small class="text-muted">— confirmed POs of the buyer not on another LC; tick the ones this LC covers</small></h6>
    <div class="small">Picked PO value: <strong data-lc-po-value>0.00</strong> · LC value: <strong data-lc-value-show>0.00</strong> · <span data-lc-diff></span></div>
</div>
@error('po_ids')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
        <thead><tr><th style="width:40px"><input type="checkbox" data-lc-all title="All"></th><th>Order</th><th>PO No</th><th>Style</th><th>Color</th><th class="text-right">Qty</th><th class="text-right">FOB</th><th class="text-right">Value</th><th>PCD</th><th>Shipment</th></tr></thead>
        <tbody id="lcPoBody">
            @foreach($pos as $po)
                @php $value = (int) $po->po_qty * (float) $po->unit_price; @endphp
                <tr data-buyer="{{ $po->order->buyer_id }}" data-value="{{ $value }}">
                    <td class="text-center"><input type="checkbox" name="po_ids[]" value="{{ $po->id }}" data-lc-po @checked(in_array($po->id, $pickedPoIds, true))></td>
                    <td>{{ $po->order->order_no ?? '' }}</td>
                    <td>{{ $po->po_no }}</td>
                    <td>{{ $po->style->style_no ?? '-' }}</td>
                    <td>{{ $po->color->name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($po->po_qty) }}</td>
                    <td class="text-right">{{ number_format((float) $po->unit_price, 4) }}</td>
                    <td class="text-right">{{ number_format($value, 2) }}</td>
                    <td>{{ $po->pcd_date?->format('d M Y') ?? '-' }}</td>
                    <td>{{ $po->shipment_date?->format('d M Y') ?? '-' }}</td>
                </tr>
            @endforeach
            <tr data-lc-empty><td colspan="10" class="text-center text-muted">Pick a buyer — its confirmed POs show here.</td></tr>
        </tbody>
    </table>
</div>

@push('js')
<script>
(function () {
    const body = document.getElementById('lcPoBody');
    const fmt = (v) => v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function buyer() { return document.querySelector('[name="buyer_id"]').value; }

    // Only the picked buyer's POs; another buyer's ticks are cleared.
    function filter() {
        let shown = 0;
        body.querySelectorAll('tr[data-buyer]').forEach(function (tr) {
            const on = buyer() !== '' && tr.dataset.buyer === buyer();
            tr.classList.toggle('d-none', ! on);
            if (! on) tr.querySelector('[data-lc-po]').checked = false;
            if (on) shown++;
        });
        body.querySelector('[data-lc-empty]').classList.toggle('d-none', shown > 0);
        recalc();
    }
    function recalc() {
        let total = 0;
        body.querySelectorAll('[data-lc-po]:checked').forEach(cb => total += parseFloat(cb.closest('tr').dataset.value) || 0);
        const lcValue = parseFloat(document.querySelector('[data-lc-value]').value) || 0;
        document.querySelector('[data-lc-po-value]').textContent = fmt(total);
        document.querySelector('[data-lc-value-show]').textContent = fmt(lcValue);
        const diff = lcValue - total;
        const el = document.querySelector('[data-lc-diff]');
        el.textContent = total && lcValue ? (Math.abs(diff) < 0.01 ? 'matches' : (diff > 0 ? 'LC is ' + fmt(diff) + ' more' : 'POs are ' + fmt(-diff) + ' more than the LC')) : '';
        el.className = diff < -0.01 ? 'text-danger' : 'text-muted';
    }
    document.querySelector('[data-lc-all]').addEventListener('change', function () {
        body.querySelectorAll('tr[data-buyer]:not(.d-none) [data-lc-po]').forEach(cb => cb.checked = this.checked);
        recalc();
    });
    body.addEventListener('change', recalc);
    document.querySelector('[data-lc-value]').addEventListener('input', recalc);
    $(document).on('change', '[name="buyer_id"]', filter);
    $(filter);
})();
</script>
@endpush
