{{--
    Hourly Output modal: one hour's output / reject / rework per size + the line's day plan.
    props: date, pos, lines, sizeRows, sizeNames, plans (line|po => plan of the date), defaults (po => line => plan), slots, breakHour, selectedHour
--}}
@php $mine = old('_modal') === 'hourly'; @endphp
<div class="modal fade" id="sewHourlyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('msfl.production.sewing.hourly.store') }}" id="sewHourlyForm">
                @csrf
                <input type="hidden" name="_modal" value="hourly">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa-solid fa-clock"></i> Hourly Output</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    @if($mine && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
                    <div class="row">
                        @include('merchandising-sfl::admin.production.sewing.partials.header-fields', ['prefix' => 'sewHourly', 'mine' => $mine])
                    </div>
                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Hour <span class="text-danger">*</span></label>
                            <select name="hour_slot" id="sewHourlyHour" class="form-control form-control-sm" required>
                                @foreach($slots as $h => $slotLabel)
                                    @continue($h === $breakHour)
                                    <option value="{{ $h }}" @selected((int) ($mine ? old('hour_slot') : $selectedHour) === $h)>{{ $slotLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        @foreach(['target' => 'Target / day', 'working_hours' => 'Hours', 'smv' => 'SMV', 'operators' => 'Operators', 'helpers' => 'Helpers'] as $k => $l)
                            <div class="col-md-{{ $k === 'target' ? 2 : 1 }} mb-3 {{ $k === 'target' ? '' : 'px-1' }}" style="{{ $k === 'target' ? '' : 'flex:0 0 11%;max-width:11%' }}">
                                <label class="form-label">{{ $l }} <span class="text-danger">*</span></label>
                                <input type="number" min="0" step="any" name="{{ $k }}" class="form-control form-control-sm" data-plan="{{ $k }}" value="{{ $mine ? old($k) : '' }}" required>
                            </div>
                        @endforeach
                        <div class="col-md-2 mb-3">
                            <label class="form-label">Manpower / per hour</label>
                            <div class="form-control form-control-sm bg-light" id="sewHourlyCalc">—</div>
                        </div>
                    </div>
                    <p class="small text-muted mt-n2 mb-2" id="sewHourlyNote"></p>
                    <table class="table table-bordered table-sm align-middle mb-2">
                        <thead><tr><th style="width:90px">Size</th><th>In the line</th><th style="width:130px">Output</th><th style="width:130px">Reject</th><th style="width:130px">Rework</th></tr></thead>
                        <tbody id="sewHourlySizeRows"></tbody>
                        <tfoot><tr><td colspan="2" class="text-right"><strong>Total</strong></td>
                            <td><strong data-total="pass_qty">0</strong></td><td><strong data-total="reject_qty" class="text-danger">0</strong></td><td><strong data-total="rework_qty">0</strong></td></tr></tfoot>
                    </table>
                    <p class="small text-muted mb-2">Rework stays in the line until it passes (enter it as output in a later hour). Leave the sizes empty to save only the line plan.</p>
                    <input type="text" name="remarks" class="form-control form-control-sm" placeholder="Remarks" value="{{ $mine ? old('remarks') : '' }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                    <button type="submit" name="add_another" value="1" class="btn btn-sm ui-act ui-act-save" title="Save & Next Hour"><i class="fa-solid fa-floppy-disk"></i> Save &amp; Next Hour</button>
                    <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('merchandising-sfl::admin.production.sewing.partials.size-script', [
    'prefix' => 'sewHourly', 'mine' => $mine,
    'fields' => ['pass_qty' => 'Output', 'reject_qty' => 'Reject', 'rework_qty' => 'Rework'],
    'info' => "return 'In the line (WIP): <strong>' + r.wip + '</strong> · Sewn so far: ' + r.pass + ' · Order: ' + r.ordered;",
])
@push('js')
<script>
(function () {
    // Plan: this line's saved plan of the day, else defaults (Bulletin → style SMV → line figures).
    const plans = @json($plans);
    const defaults = @json($defaults);
    const keepOld = @json($mine);
    const po = document.getElementById('sewHourlyPo');
    const line = document.getElementById('sewHourlyLine');
    const note = document.getElementById('sewHourlyNote');
    const field = k => document.querySelector('#sewHourlyForm [data-plan="' + k + '"]');

    function calc() {
        const mp = parseInt(field('operators').value || 0, 10) + parseInt(field('helpers').value || 0, 10);
        const hrs = parseFloat(field('working_hours').value || 0);
        document.getElementById('sewHourlyCalc').textContent = mp + ' / ' + (hrs > 0 ? Math.round(parseInt(field('target').value || 0, 10) / hrs) : 0);
    }
    function fill(first) {
        if (first && keepOld) { calc(); return; }
        const key = line.value + '|' + po.value;
        const plan = plans[key] || ((defaults[po.value] || {})[line.value]);
        note.textContent = plans[key] ? 'Line plan saved for this day — changing it updates the plan.' : (plan ? 'Line plan from the Bulletin / line — check and change if needed.' : '');
        if (plan) { ['target', 'working_hours', 'smv', 'operators', 'helpers'].forEach(k => field(k).value = plan[k] ?? 0); }
        calc();
    }
    document.getElementById('sewHourlyForm').addEventListener('input', e => { if (e.target.matches('[data-plan]')) calc(); });
    $(po).on('change', () => fill(false));
    $(line).on('change', () => fill(false));
    fill(true);
    window.sewHourlyFill = () => fill(false);
})();
</script>
@endpush
