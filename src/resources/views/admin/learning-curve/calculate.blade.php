@extends(adminTheme() . 'layouts.app')

@section('title')
    <title>{{ websiteTitle('Learning Curve Calculator') }}</title>
@endsection

@section('contents')
<div class="flex-grow-1 msfl-module">
    @include('merchandising-sfl::admin.partials.alerts')
    @include('merchandising-sfl::admin.partials.ui-kit')

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Learning Curve Calculator</h4>
            <a href="{{ route('msfl.learning-curve.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <p class="text-muted small">এটা শুধু একটা হিসাব-যন্ত্র — এখানে যা দেখাবে তা কোথাও সেভ হয় না, T&amp;A বা Sewing Board-এর কোনো হিসাবও এখনো এটা বদলায় না।</p>

            <div class="form-row">
                @if($canShipment)
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Curve Type</label>
                        <select id="lcType" class="form-control form-control-sm">
                            <option value="production">Production (সবাই দেখবে)</option>
                            <option value="shipment">Shipment (Planning Manager — rough)</option>
                        </select>
                    </div>
                @endif
                <div class="col-md-3 mb-3">
                    <label class="form-label">Style <small class="text-muted">(SMV auto আনতে, optional)</small></label>
                    <select id="lcStyle" class="form-control form-control-sm msfl-select2">
                        <option value="">— Manual SMV —</option>
                        @foreach($styles as $style)
                            <option value="{{ $style->id }}" data-smv="{{ $style->smv }}">{{ $style->style_no }} — {{ $style->name }} (SMV {{ $style->smv }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">SMV</label>
                    <input type="number" step="0.01" min="0" id="lcSmv" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Manpower</label>
                    <input type="number" step="1" min="1" id="lcManpower" value="45" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Working Hours / Day</label>
                    <input type="number" step="0.5" min="1" id="lcHours" value="8" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Order Qty <small class="text-muted">(optional)</small></label>
                    <input type="number" step="1" min="0" id="lcOrderQty" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Start Date <small class="text-muted">(optional)</small></label>
                    <input type="date" id="lcStartDate" class="form-control form-control-sm">
                </div>
                <div id="lcShipmentFields" class="col-md-12" style="display:none">
                    <div class="form-row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Washing</label>
                            <select id="lcWashing" class="form-control form-control-sm">
                                <option value="">— None —</option>
                                @foreach($leadTimes->where('category', 'washing') as $lt)
                                    <option value="{{ $lt->days }}">{{ $lt->name }} ({{ $lt->days }} day)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Finishing</label>
                            <select id="lcFinishing" class="form-control form-control-sm">
                                <option value="">— None —</option>
                                @foreach($leadTimes->where('category', 'finishing') as $lt)
                                    <option value="{{ $lt->days }}">{{ $lt->name }} ({{ $lt->days }} day)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div id="lcNoBand" class="alert alert-warning py-1 px-2" style="display:none">এই SMV-এর জন্য কোনো band পাওয়া যায়নি (SMV 0 বা খালি, অথবা ৬০-এর বেশি) — Learning Curve master data-তে band যোগ করুন।</div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center align-middle" id="lcTable">
                    <thead>
                        <tr>
                            <th>Day</th><th>Date</th><th>Efficiency %</th><th>Day Qty</th><th>Cumulative Qty</th>
                        </tr>
                    </thead>
                    <tbody id="lcRows"></tbody>
                    <tfoot>
                        <tr id="lcTargetRow" style="display:none">
                            <td colspan="5" class="text-left font-weight-bold" id="lcTargetMsg"></td>
                        </tr>
                        <tr id="lcReadyRow" style="display:none">
                            <td colspan="5" class="text-left font-weight-bold text-success" id="lcReadyMsg"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

@include('merchandising-sfl::admin.partials.select2-init')
@push('js')
<script>
(function () {
    const productionBands = {!! $productionBands->map(fn ($b) => ['smv_from' => $b->smv_from, 'smv_to' => $b->smv_to, 'label' => $b->label, 'day_percents' => $b->day_percents])->toJson() !!};
    const shipmentBands = {!! $shipmentBands->map(fn ($b) => ['smv_from' => $b->smv_from, 'smv_to' => $b->smv_to, 'label' => $b->label, 'day_percents' => $b->day_percents])->toJson() !!};

    function findBand(bands, smv) {
        return bands.find(function (b) {
            const minOk = b.smv_from === null || smv >= parseFloat(b.smv_from);
            const maxOk = b.smv_to === null || smv <= parseFloat(b.smv_to);
            return minOk && maxOk;
        });
    }

    const typeSelect = document.getElementById('lcType');
    const styleSelect = document.getElementById('lcStyle');
    const smvInput = document.getElementById('lcSmv');
    const manpowerInput = document.getElementById('lcManpower');
    const hoursInput = document.getElementById('lcHours');
    const orderQtyInput = document.getElementById('lcOrderQty');
    const startDateInput = document.getElementById('lcStartDate');
    const washingSelect = document.getElementById('lcWashing');
    const finishingSelect = document.getElementById('lcFinishing');
    const shipmentFields = document.getElementById('lcShipmentFields');

    $(styleSelect).on('change', function () {
        const opt = this.selectedOptions[0];
        if (opt && opt.dataset.smv) { smvInput.value = opt.dataset.smv; recalc(); }
    });

    function addDays(dateStr, days) {
        if (! dateStr) return null;
        const d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + days);
        return d;
    }
    function fmt(d) {
        if (! d) return '';
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function recalc() {
        const type = typeSelect ? typeSelect.value : 'production';
        shipmentFields.style.display = type === 'shipment' ? '' : 'none';

        const smv = parseFloat(smvInput.value) || 0;
        const manpower = parseFloat(manpowerInput.value) || 0;
        const hours = parseFloat(hoursInput.value) || 0;
        const orderQty = parseFloat(orderQtyInput.value) || 0;

        const band = smv > 0 ? findBand(type === 'shipment' ? shipmentBands : productionBands, smv) : null;
        document.getElementById('lcNoBand').style.display = (smv > 0 && ! band) ? '' : 'none';

        const rowsBody = document.getElementById('lcRows');
        rowsBody.innerHTML = '';
        document.getElementById('lcTargetRow').style.display = 'none';
        document.getElementById('lcReadyRow').style.display = 'none';
        if (! band || manpower <= 0 || hours <= 0) return;

        const baseQtyPerDay = (manpower * hours * 60) / smv; // capacity at 100% efficiency
        let cumulative = 0;
        let targetDay = null;
        let targetDate = null;
        let lastDate = null;

        band.day_percents.forEach(function (pct, i) {
            const day = i + 1;
            const dayQty = Math.round(baseQtyPerDay * (pct / 100));
            cumulative += dayQty;
            const date = startDateInput.value ? addDays(startDateInput.value, day - 1) : null;
            if (date) lastDate = date;
            if (! targetDay && orderQty > 0 && cumulative >= orderQty) { targetDay = day; targetDate = date; }

            const tr = document.createElement('tr');
            if (targetDay === day) tr.classList.add('table-success');
            tr.innerHTML = '<td>' + day + '</td><td>' + fmt(date) + '</td><td>' + pct + '%</td><td>' + dayQty.toLocaleString() + '</td><td>' + cumulative.toLocaleString() + '</td>';
            rowsBody.appendChild(tr);
        });

        if (orderQty > 0) {
            const msg = targetDay
                ? ('Order Qty ' + orderQty.toLocaleString() + ' — Day ' + targetDay + (targetDate ? ' (' + fmt(targetDate) + ')' : '') + '-তে sewing শেষ হবে (cumulative ' + cumulative.toLocaleString() + ')।')
                : ('এই ' + band.day_percents.length + ' দিনে cumulative ' + cumulative.toLocaleString() + ' হবে — Order Qty ' + orderQty.toLocaleString() + '-এ পৌঁছায়নি, আরও দিন লাগবে (বর্তমান curve-এ শেষ দিনের % ধরে হিসাব করুন)।');
            document.getElementById('lcTargetMsg').textContent = msg;
            document.getElementById('lcTargetRow').style.display = '';
        }

        if (type === 'shipment' && lastDate) {
            const washDays = parseInt(washingSelect.value) || 0;
            const finishDays = parseInt(finishingSelect.value) || 0;
            const readyDate = new Date(lastDate);
            readyDate.setDate(readyDate.getDate() + washDays + finishDays);
            document.getElementById('lcReadyMsg').textContent = 'Sewing শেষের পর Washing (' + washDays + ' day) + Finishing (' + finishDays + ' day) যোগ করে Ready Date আনুমানিক: ' + fmt(readyDate);
            document.getElementById('lcReadyRow').style.display = '';
        }
    }

    [typeSelect, smvInput, manpowerInput, hoursInput, orderQtyInput, startDateInput, washingSelect, finishingSelect].forEach(function (el) {
        if (el) el.addEventListener('input', recalc);
        if (el) el.addEventListener('change', recalc);
    });
    recalc();
})();
</script>
@endpush
@endsection
