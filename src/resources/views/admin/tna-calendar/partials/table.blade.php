{{--
    T&A Calendar table — one row per milestone, the client's 17 columns.
    props: plans, calendar, print (bool: links off, iteration numbers on)
    * columns have no entry in the system yet and stay empty.
--}}
@php $print = $print ?? false; $n = method_exists($plans, 'firstItem') ? max(0, (int) $plans->firstItem() - 1) : 0; @endphp
@php $badge = ['green' => 'success', 'amber' => 'warning', 'red' => 'danger', 'grey' => 'light', '' => 'secondary']; @endphp
<table class="{{ $print ? 'tp' : 'table table-bordered table-sm align-middle tna-cal' }}">
    <thead>
        <tr>
            <th>#</th>
            <th>Buyer</th>
            <th>Style</th>
            <th>PO</th>
            <th>Order Qty</th>
            <th>Order Confirmation</th>
            <th>Ex-Factory Date</th>
            <th>Activity / Milestone</th>
            <th class="no-entry" title="No entry in the system yet">Responsible Department *</th>
            <th>Planned Date</th>
            <th>Actual Date</th>
            <th>Status</th>
            <th>Delay Days</th>
            <th class="no-entry" title="No entry in the system yet">Dependency *</th>
            <th class="no-entry" title="No entry in the system yet">Corrective Action *</th>
            <th>Action Owner</th>
            <th>Revised Date</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @forelse($plans as $plan)
            @php $tasks = $plan->tasks; $span = max(1, $tasks->count()); @endphp
            @foreach($tasks->isEmpty() ? [null] : $tasks as $task)
                <tr class="{{ $loop->first ? 'plan-first' : '' }}">
                    @if($loop->first)
                        <td rowspan="{{ $span }}" class="c">{{ ++$n }}</td>
                        <td rowspan="{{ $span }}">{{ $plan->order->buyer->name ?? '' }}</td>
                        <td rowspan="{{ $span }}">
                            {{ $plan->style->style_no ?? '' }}
                            @unless($print)<div class="small"><a href="{{ route('msfl.tna.show', $plan) }}">{{ $plan->tna_no }}</a></div>@endunless
                        </td>
                        <td rowspan="{{ $span }}" class="wrap">{{ $calendar->poNumbers($plan) }}</td>
                        <td rowspan="{{ $span }}" class="r">{{ number_format($plan->order_qty) }}</td>
                        <td rowspan="{{ $span }}" class="c">{{ $plan->order->confirmed_at?->format('d-M-y') }}</td>
                        <td rowspan="{{ $span }}" class="c">{{ $plan->ex_factory_date?->format('d-M-y') }}</td>
                    @endif
                    @if($task)
                        @php [$label, $color] = $calendar->status($task); $delay = $calendar->delayDays($task); @endphp
                        <td class="wrap">{{ $task->task_name }}</td>
                        <td class="no-entry"></td>
                        <td class="c">{{ $task->plan_date?->format('d-M-y') }}</td>
                        <td class="c">{{ $task->actual_date?->format('d-M-y') }}</td>
                        @if($print)
                            <td class="c c-{{ $color }}">{{ $label }}</td>
                            <td class="r @if($delay) c-red @endif">{{ $delay ?? '' }}</td>
                        @else
                            <td class="c"><span class="badge badge-{{ $badge[$color] }}">{{ $label }}</span></td>
                            <td class="r @if($delay) text-danger font-weight-bold @endif">{{ $delay ?? '' }}</td>
                        @endif
                        <td class="no-entry"></td>
                        <td class="no-entry"></td>
                        <td>{{ $task->responsible->name ?? '' }}</td>
                        <td class="c">{{ $task->revised_date?->format('d-M-y') }}</td>
                        <td class="wrap">{{ $task->remarks }}</td>
                    @else
                        <td colspan="11" class="c muted">No milestone</td>
                    @endif
                </tr>
            @endforeach
        @empty
            <tr><td colspan="18" class="c muted">No T&amp;A found — confirm an order, then create its T&amp;A.</td></tr>
        @endforelse
    </tbody>
</table>
<div class="tna-note">* Responsible Department, Dependency and Corrective Action are not entered in the system yet. Delay Days = calendar days after the revised (or planned) date.</div>
