@php $printMode = request()->boolean('print'); @endphp
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'T&A ' . $tna->tna_no }}@else
    <title>{{ websiteTitle('T&A ' . $tna->tna_no) }}</title>
@endif
@endsection

@php
    $editable = ! $printMode && $tna->isEditable() && auth()->user()->can('msfl_tna.edit');
    [$done, $total] = $tna->progress();
    $milestones = [
        'Order Confirmed' => $tna->order->confirmed_at,
        'PCD (Cutting)' => $tna->pcd_date,
        'Sewing Start' => $tna->sewing_start_date,
        'Sewing End' => $tna->sewing_end_date,
        'Ex-Factory' => $tna->ex_factory_date,
        'Shipment' => $tna->shipment_date,
    ];
@endphp

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'T&A — ' . $tna->tna_no, 'printPage' => 'A4 landscape'])
    <style>
        .tna-timeline { display: flex; flex-wrap: wrap; gap: .25rem; }
        .tna-timeline .step { flex: 1 1 120px; border: 1px solid #dee2e6; border-radius: .25rem; padding: .4rem; text-align: center; background: #f8f9fa; }
        .tna-timeline .step.past { background: #e9f7ef; }
        .tna-grid td { font-size: .85rem; }
        .tna-grid input.form-control-sm, .tna-grid select.form-control-sm { min-width: 120px; }
    </style>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">T&amp;A — {{ $tna->tna_no }} @include('merchandising-sfl::admin.partials.status-badge', ['model' => $tna])</h4>
            <div>
                @include('merchandising-sfl::admin.partials.print-button')
                @if($editable)
                    <button type="button" class="btn btn-sm ui-act ui-act-reset" data-toggle="modal" data-target="#recalcModal" title="Recalculate"><i class="fa-solid fa-rotate-left"></i> Recalculate</button>
                @endif
                @can('msfl_tna.edit')
                    @foreach($tna->status === 'active' ? ['completed' => ['Mark Completed', 'approve', 'fa-check'], 'cancelled' => ['Cancel', 'cancel', 'fa-ban']] : ['active' => ['Re-open', 'unlock', 'fa-lock-open']] as $status => [$label, $act, $icon])
                        <form method="POST" action="{{ route('msfl.tna.status', $tna) }}" class="d-inline" onsubmit="return confirm('{{ $label }}?');">
                            @csrf <input type="hidden" name="status" value="{{ $status }}">
                            <button type="submit" class="btn btn-sm ui-act ui-act-{{ $act }}" title="{{ $label }}"><i class="fa-solid {{ $icon }}"></i> {{ $label }}</button>
                        </form>
                    @endforeach
                @endcan
                <a href="{{ route('msfl.tna.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-3 mb-2"><strong>Order:</strong> <a href="{{ route('msfl.orders.show', $tna->order) }}">{{ $tna->order->order_no }}</a></div>
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $tna->order->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Style:</strong> <a href="{{ route('msfl.styles.show', $tna->style) }}">{{ $tna->style->label() }}</a></div>
                <div class="col-md-3 mb-2"><strong>Progress:</strong> {{ $done }}/{{ $total }} steps done
                    @if($tna->delayedCount())<span class="badge badge-danger">{{ $tna->delayedCount() }} delayed</span>@endif
                </div>
                <div class="col-md-3 mb-2"><strong>Order Qty:</strong> {{ number_format($tna->order_qty) }} pcs</div>
                <div class="col-md-3 mb-2"><strong>Template:</strong> {{ $tna->template->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>SMV:</strong> {{ (float) $tna->smv }}
                    <small class="text-muted">({!! $tna->bulletin ? 'from <a href="' . route('msfl.bulletins.show', $tna->bulletin) . '">' . e($tna->bulletin->bulletin_no) . '</a>' : 'from style' !!})</small>
                </div>
                <div class="col-md-3 mb-2"><strong>Last calculated:</strong> {{ optional($tna->calculated_at)->format('d M Y H:i') }}</div>
            </div>

            {{-- Capacity --}}
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Capacity &amp; Sewing Days</strong></div>
                <div class="card-body py-2">
                    <table class="table table-bordered table-sm mb-2">
                        <thead><tr><th>Line</th><th>Operators</th><th>Minutes / Day</th><th>Efficiency</th><th>Calculation</th><th class="text-right">Pieces / Day</th></tr></thead>
                        <tbody>
                            @foreach($tna->lines as $line)
                                <tr>
                                    <td>{{ $line->name }}</td>
                                    <td>{{ $line->operators }}</td>
                                    <td>{{ $line->working_minutes }}</td>
                                    <td>{{ (float) $line->efficiency_percent }}%</td>
                                    <td class="text-muted">{{ $line->operators }} × {{ $line->working_minutes }} × {{ (float) $line->efficiency_percent }}% ÷ {{ (float) $tna->smv }}</td>
                                    <td class="text-right">{{ number_format($line->pivot->daily_capacity) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="5" class="text-right">Daily capacity</th><th class="text-right">{{ number_format($tna->daily_capacity) }}</th></tr>
                            <tr><th colspan="5" class="text-right">Sewing days = ⌈ {{ number_format($tna->order_qty) }} ÷ {{ number_format($tna->daily_capacity) }} ⌉</th><th class="text-right">{{ $tna->sewing_days }} working days</th></tr>
                        </tfoot>
                    </table>

                    <div class="tna-timeline">
                        @foreach($milestones as $label => $date)
                            <div @class(['step', 'past' => $date && $date->lt(today())])>
                                <small class="text-muted d-block">{{ $label }}</small>
                                <strong>{{ $date ? $date->format('d M Y') : '—' }}</strong>
                                @if($date)<small class="d-block text-muted">{{ $date->format('l') }}</small>@endif
                            </div>
                            @if(! $loop->last)<div class="align-self-center text-muted">→</div>@endif
                        @endforeach
                    </div>
                    <small class="text-muted">
                        Counted back from shipment in working days (weekly off &amp; holidays skipped):
                        Ex-Factory = Shipment − {{ $tna->template->ship_to_ex_factory_days }},
                        Sewing End = Ex-Factory − {{ $tna->template->ex_factory_to_sewing_end_days }},
                        Sewing Start = Sewing End − {{ max($tna->sewing_days - 1, 0) }},
                        PCD = Sewing Start − {{ $tna->template->pcd_to_sewing_start_days }}.
                    </small>
                </div>
            </div>

            @unless($tna->is_feasible)
                <div class="alert alert-danger">
                    <strong>Not achievable:</strong>
                    @if($tna->daily_capacity <= 0)
                        the selected line(s) have no capacity (check operators / minutes / SMV).
                    @else
                        cutting would have to start on {{ $tna->pcd_date->format('d M Y') }}, which has already passed.
                        @if($shortfall)
                            Only <strong>{{ $shortfall['available_days'] }}</strong> working day(s) are left for sewing, which needs
                            <strong>{{ number_format($shortfall['required_per_day']) }}</strong> pcs/day — currently {{ number_format($tna->daily_capacity) }}
                            (short by {{ number_format($shortfall['shortfall_per_day']) }} pcs/day). Add line(s) with Recalculate, or move the shipment date.
                        @endif
                    @endif
                </div>
            @endunless

            @if($conflicts->isNotEmpty())
                <div class="alert alert-warning">
                    <strong>Line conflict:</strong> these lines are already loaded during this sewing window ({{ $tna->sewing_start_date->format('d M') }} → {{ $tna->sewing_end_date->format('d M') }}):
                    <ul class="mb-0">
                        @foreach($conflicts as $conflict)
                            <li>{{ $conflict['line'] }} — <a href="{{ route('msfl.tna.show', $conflict['plan']) }}">{{ $conflict['plan']->tna_no }}</a>
                                ({{ $conflict['plan']->style->style_no ?? '' }}, sewing {{ $conflict['plan']->sewing_start_date->format('d M') }} → {{ $conflict['plan']->sewing_end_date->format('d M') }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Steps --}}
            <form method="POST" action="{{ route('msfl.tna.tasks.update', $tna) }}">
                @csrf @method('PUT')
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Steps
                        <small class="text-muted">— Plan = auto; Revised = new agreed date; Actual = when it was really done.</small>
                    </h6>
                    <div class="small">
                        @foreach(\ME\MerchandisingSfl\Models\TnaTask::STATES as [$label, $color])
                            <span class="badge badge-{{ $color }}">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle tna-grid">
                        <thead>
                            <tr><th>#</th><th>Step</th><th>Rule</th><th>Plan</th><th>Revised</th><th>Actual</th><th>Status</th><th>N/A</th><th>Responsible</th><th>Remarks</th></tr>
                        </thead>
                        <tbody>
                            @foreach($tna->tasks->groupBy('group_name') as $group => $tasks)
                                <tr class="table-secondary"><th colspan="10">{{ $group }}</th></tr>
                                @foreach($tasks as $task)
                                    @php $f = 'tasks[' . $task->id . ']'; @endphp
                                    <tr @class(['table-danger' => $task->state() === 'delayed', 'text-muted' => $task->is_na])>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="text-nowrap">
                                            {{ $task->task_name }}
                                            @if($task->is_mandatory)<span class="text-danger" title="Mandatory">*</span>@endif
                                            @if($task->isAuto())<span class="badge badge-info" title="Actual date fills automatically from {{ $task->auto_source }}">auto</span>@endif
                                        </td>
                                        <td class="text-nowrap text-muted small">{{ $task->ruleText() }}</td>
                                        <td class="text-nowrap">{{ optional($task->plan_date)->format('d M Y') ?? '-' }}</td>
                                        @if($editable)
                                            <td><input type="date" name="{{ $f }}[revised_date]" class="form-control form-control-sm" value="{{ optional($task->revised_date)->format('Y-m-d') }}"></td>
                                            <td><input type="date" name="{{ $f }}[actual_date]" class="form-control form-control-sm" value="{{ optional($task->actual_date)->format('Y-m-d') }}"></td>
                                        @else
                                            <td class="text-nowrap">{{ optional($task->revised_date)->format('d M Y') ?? '-' }}</td>
                                            <td class="text-nowrap">{{ optional($task->actual_date)->format('d M Y') ?? '-' }}</td>
                                        @endif
                                        <td><span class="badge badge-{{ $task->stateBadge() }}">{{ $task->stateLabel() }}</span></td>
                                        @if($editable)
                                            <td class="text-center">
                                                <input type="hidden" name="{{ $f }}[is_na]" value="0">
                                                <input type="checkbox" name="{{ $f }}[is_na]" value="1" @checked($task->is_na)>
                                            </td>
                                            <td>
                                                <select name="{{ $f }}[responsible_id]" class="form-control form-control-sm">
                                                    <option value=""></option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}" @selected((int) $task->responsible_id === (int) $user->id)>{{ $user->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input type="text" name="{{ $f }}[remarks]" class="form-control form-control-sm" value="{{ $task->remarks }}"></td>
                                        @else
                                            <td class="text-center">{{ $task->is_na ? '✓' : '' }}</td>
                                            <td>{{ $task->responsible->name ?? '-' }}</td>
                                            <td>{{ $task->remarks }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($editable)
                    <button type="submit" class="btn btn-sm ui-act ui-act-save" title="Save Updates"><i class="fa-solid fa-floppy-disk"></i> Save Updates</button>
                @endif
            </form>
        </div>
    </div>
</div>

@if($editable)
    <div class="modal fade" id="recalcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('msfl.tna.recalculate', $tna) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Recalculate T&amp;A</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">
                            Takes the latest order qty, shipment date and SMV (approved bulletin) from the order, works the dates out again,
                            and moves the planned date of every step that is not done yet. Actual and revised dates are kept.
                        </p>
                        <label class="form-label">Line(s)</label>
                        <select name="line_ids[]" class="form-control form-control-sm msfl-select2" multiple>
                            @foreach($lines as $line)
                                <option value="{{ $line->id }}" @selected($tna->lines->contains('id', $line->id))>{{ $line->name }} ({{ $line->operators }} op · {{ (float) $line->efficiency_percent }}%)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm ui-act ui-act-cancel" data-dismiss="modal" title="Cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                        <button type="submit" class="btn btn-sm ui-act ui-act-auto" title="Recalculate"><i class="fa-solid fa-wand-magic-sparkles"></i> Recalculate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@include('merchandising-sfl::admin.partials.select2-init')
@endsection
