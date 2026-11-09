{{--
    Open Cost Sheet entry, laid out like the printed sheet (partials/sheet).
    props: costSheet (model, may be unsaved), buyers, styles, inquiries, currencies, itemOptions, uoms, suppliers (id => name)
    Every line is per DOZEN: total = consumption / dz × unit price. CM / dz = SMV ÷ efficiency × CPM × 12 unless typed.
--}}
@php
    $val = fn (string $field, $default = null) => old($field, $costSheet->{$field} ?? $default);
    $lines = collect(old('items', $costSheet->relationLoaded('items') ? $costSheet->items->toArray() : []))->groupBy('group');
    $groupsMeta = \ME\MerchandisingSfl\Models\CostSheet::GROUPS;
    $rowProps = compact('itemOptions', 'uoms', 'suppliers');
    $date = $val('costing_date');
    // A stored CM equal to the SMV × CPM formula was derived: keep it following SMV / CPM.
    $cmManual = (float) $val('cm_cost', 0) > 0 && ! ($costSheet->exists && old('cm_cost') === null && $costSheet->cmFromMinutes() !== null
        && abs((float) $costSheet->cm_cost - $costSheet->cmFromMinutes()) < 0.0001);
@endphp

<div class="csf">
    {{-- Costing basis: pick the tech pack and/or inquiry — the rest fills in --}}
    <div class="row">
        @include('merchandising-sfl::admin.partials.select', ['name' => 'style_id', 'label' => 'Tech Pack / Style', 'options' => $styles->mapWithKeys(fn ($s) => [$s->id => $s->label()]), 'value' => $costSheet->style_id, 'placeholder' => '— None (style not created yet) —'])
        @include('merchandising-sfl::admin.partials.select', ['name' => 'inquiry_id', 'label' => 'Inquiry', 'options' => $inquiries->mapWithKeys(fn ($i) => [$i->id => $i->inquiry_no . ($i->style_ref ? ' — ' . $i->style_ref : '')]), 'value' => $costSheet->inquiry_id, 'placeholder' => '— None —'])
        <div class="col-md-6 mb-3 d-flex align-items-end small text-muted">Pick a tech pack or an inquiry — buyer, style, qty, SMV, target price and sizes fill in. With neither, type the style below.</div>
    </div>

    <div class="csf-title">
        <div class="csf-co">{{ general()->title ?? '' }}</div>
        <div class="csf-sub">OPEN COST SHEET</div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <table class="table table-bordered table-sm csf-head">
                <tr>
                    <th>Buyer <span class="text-danger">*</span></th>
                    <td>
                        <select name="buyer_id" class="form-control form-control-sm msfl-select2" required>
                            <option value="">— Select —</option>
                            @foreach($buyers as $b)
                                <option value="{{ $b->id }}" @selected((string) $val('buyer_id') === (string) $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><th>Description</th><td><input type="text" name="garment_description" class="form-control form-control-sm" maxlength="255" placeholder="e.g. DENIM JACKET" value="{{ $val('garment_description') }}"></td></tr>
                <tr><th>Style</th><td><input type="text" name="style_ref" class="form-control form-control-sm" maxlength="150" value="{{ $val('style_ref') }}"></td></tr>
                <tr><th>Size</th><td><input type="text" name="size_range" class="form-control form-control-sm" maxlength="100" placeholder="e.g. S-XL" value="{{ $val('size_range') }}"></td></tr>
                <tr>
                    <th>Order</th>
                    <td><div class="input-group input-group-sm"><input type="number" min="0" name="order_qty" class="form-control form-control-sm" value="{{ $val('order_qty') }}"><div class="input-group-append"><span class="input-group-text">Pcs</span></div></div></td>
                </tr>
            </table>
        </div>
        <div class="col-lg-5">
            <table class="table table-bordered table-sm csf-head">
                <tr><th>Date <span class="text-danger">*</span></th><td><input type="date" name="costing_date" class="form-control form-control-sm" required value="{{ $date instanceof \Carbon\CarbonInterface ? $date->format('Y-m-d') : $date }}"></td></tr>
                <tr>
                    <th>Currency</th>
                    <td>
                        <select name="currency_id" class="form-control form-control-sm msfl-select2">
                            <option value="">— Select —</option>
                            @foreach($currencies as $c)
                                <option value="{{ $c->id }}" @selected((string) $val('currency_id') === (string) $c->id)>{{ $c->code }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Price Type</th>
                    <td>
                        <select name="price_type" class="form-control form-control-sm">
                            <option value="">—</option>
                            @foreach(\ME\MerchandisingSfl\Models\CostSheet::PRICE_TYPES as $pt)
                                <option value="{{ $pt }}" @selected($val('price_type') === $pt)>{{ $pt }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
                <tr><th>Buyer Target <small class="text-muted">/Pc</small></th><td><input type="number" step="any" min="0" name="buyer_target_price" class="form-control form-control-sm" value="{{ $val('buyer_target_price') }}" data-cs-target></td></tr>
                <tr><th>Final Price <small class="text-muted">/Pc</small></th><td><input type="number" step="any" min="0" name="final_price" class="form-control form-control-sm" value="{{ $val('final_price') }}"></td></tr>
                <tr><td colspan="2" class="small text-muted">Front / back / sketch pictures come from the style's Tech Pack.</td></tr>
            </table>
        </div>
    </div>

    {{-- A–G sections --}}
    @foreach($groupsMeta as $group => [$letter, $title, $totalLabel])
        <div class="csf-section">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-1">{{ $letter }}. {{ $title }} <small class="text-muted">— total / dz = consumption / dz × unit price</small></h6>
                <button type="button" class="btn btn-sm ui-act ui-act-create" data-cs-add="{{ $group }}" title="Add Row"><i class="fa-solid fa-plus"></i> Add Row</button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-1">
                    <thead>
                        <tr>
                            <th style="width:48px">SL</th><th>Item (library)</th><th>{{ $group === 'fabric' ? 'Fabric' : 'Details' }}</th><th>Supplier</th>
                            <th>Consumption <small class="text-muted">/Dz</small></th><th>Units</th><th>Unit Price</th><th class="text-right">Total /Dz</th><th></th>
                        </tr>
                    </thead>
                    <tbody data-cs-group="{{ $group }}">
                        @foreach($lines->get($group, collect(! $costSheet->exists && old('items') === null && in_array($group, ['fabric', 'trims'], true) ? [[]] : [])) as $i => $line)
                            @include('merchandising-sfl::admin.cost-sheets.partials.line-row', ['index' => $group . $i, 'group' => $group, 'line' => $line] + $rowProps)
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="csf-total"><td colspan="7" class="text-right">{{ $letter }}. {{ strtoupper($totalLabel) }}</td><td class="text-right" data-cs-group-total="{{ $group }}">-</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <template id="cs{{ $group }}RowTemplate">
                @include('merchandising-sfl::admin.cost-sheets.partials.line-row', ['index' => '__INDEX__', 'group' => $group, 'line' => []] + $rowProps)
            </template>
        </div>
    @endforeach

    <div class="row mt-3">
        {{-- CM & commercial inputs --}}
        <div class="col-lg-5">
            <table class="table table-bordered table-sm csf-head">
                <tr><th>SMV</th><td><input type="number" step="any" min="0" name="smv" class="form-control form-control-sm" value="{{ $val('smv') }}" data-cs-smv></td></tr>
                <tr><th>CPM <small class="text-muted">(cost / minute)</small></th><td><input type="number" step="any" min="0" name="cm_minute_rate" class="form-control form-control-sm" value="{{ $val('cm_minute_rate') }}" data-cs-cpm></td></tr>
                <tr><th>Efficiency %</th><td><input type="number" step="any" min="1" max="200" name="efficiency_percent" class="form-control form-control-sm" value="{{ $val('efficiency_percent', 100) }}" data-cs-eff></td></tr>
                <tr>
                    <th>CM / Dz</th>
                    <td>
                        <input type="number" step="any" min="0" name="cm_cost" class="form-control form-control-sm" value="{{ $val('cm_cost') }}" data-cs-cm data-manual="{{ $cmManual ? 1 : 0 }}">
                        <span class="form-text small text-muted">Auto = SMV ÷ efficiency × CPM × 12. Type a value (e.g. the tech pack's Confirm CM) to override.</span>
                    </td>
                </tr>
                <tr><th>Commercial % <small class="text-muted">(on materials)</small></th><td><input type="number" step="any" min="0" max="100" name="commercial_percent" class="form-control form-control-sm" value="{{ $val('commercial_percent', 0) }}" data-cs-commercial required></td></tr>
                <tr><th>Other / Dz <small class="text-muted">(freight, testing …)</small></th><td><input type="number" step="any" min="0" name="other_cost" class="form-control form-control-sm" value="{{ $val('other_cost', 0) }}" data-cs-other required></td></tr>
                <tr><th>Profit %</th><td><input type="number" step="any" min="0" max="100" name="profit_percent" class="form-control form-control-sm" value="{{ $val('profit_percent', 0) }}" data-cs-profit required></td></tr>
            </table>
        </div>

        {{-- Live summary — same figures as the printed sheet (CostSheet::summary()) --}}
        <div class="col-lg-7">
            <table class="table table-bordered table-sm csf-summary">
                <tr><td colspan="4" class="csf-sum-h">SUMMARY</td></tr>
                <tr class="text-primary font-weight-bold text-center"><td></td><td>DZN</td><td>PC</td><td>%</td></tr>
                @foreach($groupsMeta as $group => [$letter, , $totalLabel])
                    <tr>
                        <td>{{ $letter }}. {{ strtoupper($totalLabel) }}</td>
                        <td class="text-right" data-sum="{{ $group }}-dz"></td>
                        <td class="text-right" data-sum="{{ $group }}-pc"></td>
                        <td class="text-right text-primary" data-sum="{{ $group }}-pct"></td>
                    </tr>
                @endforeach
                <tr class="font-weight-bold"><td>TOTAL AMOUNT</td><td class="text-right" data-sum="mat-dz"></td><td class="text-right" data-sum="mat-pc"></td><td></td></tr>
                <tr><td><strong>CM</strong> <span class="float-right">SMV <span data-sum="smv"></span></span></td><td class="text-right csf-hl" data-sum="cm-dz"></td><td class="text-right" data-sum="cm-pc"></td><td class="text-right text-primary" data-sum="cm-pct"></td></tr>
                <tr class="font-weight-bold"><td>SUB TOTAL FOB PER</td><td class="text-right" data-sum="sub-dz"></td><td class="text-right" data-sum="sub-pc"></td><td></td></tr>
                <tr><td><strong>COMMERCIAL COST</strong> <span class="float-right" data-sum="com-rate"></span></td><td class="text-right" data-sum="com-dz"></td><td class="text-right" data-sum="com-pc"></td><td class="text-right text-primary" data-sum="com-pct"></td></tr>
                <tr><td>OTHER COST <small class="text-muted">(freight / testing / overhead)</small></td><td class="text-right" data-sum="other-dz"></td><td class="text-right" data-sum="other-pc"></td><td class="text-right text-primary" data-sum="other-pct"></td></tr>
                <tr><td>PROFIT <span class="float-right" data-sum="profit-rate"></span></td><td class="text-right" data-sum="profit-dz"></td><td class="text-right" data-sum="profit-pc"></td><td class="text-right text-primary" data-sum="profit-pct"></td></tr>
                <tr class="font-weight-bold"><td>TOTAL FOB PER DOZ</td><td class="text-right" data-sum="fob-dz"></td><td></td><td></td></tr>
                <tr class="font-weight-bold"><td>TOTAL FOB PER PCS</td><td class="text-right csf-hl" data-sum="fob-pc"></td><td class="text-center">TTL B2B</td><td class="text-right text-primary" data-sum="b2b"></td></tr>
                <tr><td colspan="4" class="small" data-sum="target-note"></td></tr>
            </table>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control form-control-sm" rows="2">{{ $val('remarks') }}</textarea>
    </div>
</div>

<datalist id="csSupplierList">
    @foreach($suppliers as $name)
        <option value="{{ $name }}"></option>
    @endforeach
</datalist>

@push('css')
<style>
    .csf-title { text-align: center; margin: .25rem 0 .75rem; }
    .csf-co { color: #d99a00; font-weight: 700; font-size: 1.4rem; letter-spacing: 1px; }
    .csf-sub { color: #1f4ea0; font-weight: 700; }
    .csf-head th { width: 34%; background: #f6f6f6; vertical-align: middle; font-size: .8rem; text-transform: uppercase; }
    .csf-section { border: 1px solid #dee2e6; border-radius: .4rem; padding: .5rem .75rem; margin-bottom: .75rem; }
    .csf-section thead th { background: #f6f6f6; font-size: .75rem; text-transform: uppercase; white-space: nowrap; }
    .csf-total td { background: #fff4b8; color: #1f4ea0; font-weight: 700; }
    .csf-summary td { padding: .2rem .5rem; }
    .csf-sum-h { background: #f4c542; font-weight: 700; text-align: center; }
    .csf-hl { background: #ffff00; font-weight: 700; }
</style>
@endpush

@push('js')
<script>
(function () {
    const root = document.querySelector('.csf');
    const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const fmt = (v, dp = 2) => v ? v.toLocaleString(undefined, { minimumFractionDigits: dp, maximumFractionDigits: dp }) : '-';
    const pct = (v) => v.toFixed(2) + ' %';
    const field = (key) => root.querySelector('[data-cs-' + key + ']');
    let rowIndex = 1000000;

    // ---- lines ----
    root.addEventListener('click', function (e) {
        const add = e.target.closest('[data-cs-add]');
        if (add) {
            const group = add.dataset.csAdd;
            const html = document.getElementById('cs' + group + 'RowTemplate').innerHTML.replaceAll('__INDEX__', group + rowIndex++);
            const wrap = document.createElement('table');
            wrap.innerHTML = '<tbody>' + html + '</tbody>';
            const row = wrap.querySelector('tr');
            root.querySelector('[data-cs-group="' + group + '"]').appendChild(row);
            if (typeof msflSelect2Init === 'function') msflSelect2Init(row);
            recalc();
            return;
        }
        if (e.target.closest('[data-cs-remove]')) {
            e.target.closest('tr').remove();
            recalc();
        }
    });

    // Picking a library item fills what the item master knows (only empty fields).
    $(root).on('change', '[data-cs-item]', function () {
        const opt = this.selectedOptions[0];
        const row = this.closest('tr');
        if (! opt || ! opt.value) return;
        const set = (sel, value) => { const el = row.querySelector(sel); if (el && ! el.value && value) el.value = value; };
        set('[data-cs-desc]', opt.dataset.name);
        set('[data-cs-supplier]', opt.dataset.supplier);
        set('[data-cs-uom]', opt.dataset.uom);
        set('[data-cs-rate]', opt.dataset.rate);
        recalc();
    });

    // ---- CM from SMV × CPM ÷ efficiency, unless typed ----
    const cm = field('cm');
    cm.addEventListener('input', function () { cm.dataset.manual = cm.value === '' || num(cm.value) === 0 ? '0' : '1'; });
    function autoCm() {
        if (cm.dataset.manual === '1') return;
        const smv = num(field('smv').value), cpm = num(field('cpm').value), eff = num(field('eff').value) || 100;
        if (smv && cpm) cm.value = (smv / (eff / 100) * cpm * 12).toFixed(4);
    }

    // ---- live totals (mirrors CostSheet::summary()) ----
    function recalc() {
        autoCm();
        const groups = {};
        let sl = 0;
        root.querySelectorAll('[data-cs-group]').forEach(function (body) {
            let total = 0;
            body.querySelectorAll('[data-cs-row]').forEach(function (row) {
                const line = num(row.querySelector('[data-cs-cons]').value) * num(row.querySelector('[data-cs-rate]').value);
                row.querySelector('[data-cs-amount]').textContent = fmt(line);
                row.querySelector('[data-cs-sl]').textContent = ++sl;
                total += line;
            });
            groups[body.dataset.csGroup] = total;
            root.querySelector('[data-cs-group-total="' + body.dataset.csGroup + '"]').textContent = fmt(total);
        });

        const mat = Object.values(groups).reduce((a, b) => a + b, 0);
        const cmDz = num(cm.value);
        const comRate = num(field('commercial').value);
        const com = mat * comRate / 100;
        const other = num(field('other').value);
        const total = mat + cmDz + com + other;
        const profitRate = num(field('profit').value);
        const profit = total * profitRate / 100;
        const fob = total + profit;
        const share = (v) => fob > 0 ? v / fob * 100 : 0;
        const put = (key, text) => { const el = root.querySelector('[data-sum="' + key + '"]'); if (el) el.textContent = text; };

        Object.entries(groups).forEach(function ([g, dz]) { put(g + '-dz', fmt(dz)); put(g + '-pc', fmt(dz / 12)); put(g + '-pct', pct(share(dz))); });
        put('mat-dz', fmt(mat)); put('mat-pc', fmt(mat / 12));
        put('smv', field('smv').value || '-');
        put('cm-dz', fmt(cmDz)); put('cm-pc', fmt(cmDz / 12)); put('cm-pct', pct(share(cmDz)));
        put('sub-dz', fmt(mat + cmDz)); put('sub-pc', fmt((mat + cmDz) / 12));
        put('com-rate', comRate + ' % on materials'); put('com-dz', fmt(com)); put('com-pc', fmt(com / 12)); put('com-pct', pct(share(com)));
        put('other-dz', fmt(other)); put('other-pc', fmt(other / 12)); put('other-pct', pct(share(other)));
        put('profit-rate', profitRate + ' %'); put('profit-dz', fmt(profit)); put('profit-pc', fmt(profit / 12)); put('profit-pct', pct(share(profit)));
        put('fob-dz', fmt(fob)); put('fob-pc', fmt(fob / 12, 4)); put('b2b', pct(share(mat)));

        const target = num(field('target').value);
        put('target-note', target && fob
            ? 'Buyer target ' + fmt(target, 4) + ' / pc → ' + (fob / 12 <= target ? 'within target by ' : 'over target by ') + fmt(Math.abs(target - fob / 12), 4) + ' / pc'
            : '');
    }
    root.addEventListener('input', recalc);
    $(root).on('change', 'select', recalc);
    recalc();
})();
</script>
@endpush

{{-- Style picked → buyer, inquiry, style ref, description, SMV, qty, target price, sizes, currency; inquiry → the same from the inquiry. --}}
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'style_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::styles(), 'fields' => [
    'buyer_id' => 'buyer_id', 'inquiry_id' => 'inquiry_id', 'style_ref' => 'style_ref', 'garment_description' => 'garment_description', 'smv' => 'smv',
    'order_qty' => 'order_qty', 'buyer_target_price' => 'buyer_target_price', 'size_range' => 'size_ref', 'currency_id' => 'currency_id',
]])
@include('merchandising-sfl::admin.partials.autofill', ['source' => 'inquiry_id', 'map' => \ME\MerchandisingSfl\Support\Autofill::inquiries(), 'fields' => [
    'buyer_id' => 'buyer_id', 'style_ref' => 'style_ref', 'order_qty' => 'order_qty', 'buyer_target_price' => 'unit_price', 'smv' => 'smv',
]])
