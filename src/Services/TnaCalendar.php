<?php

namespace ME\MerchandisingSfl\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\TnaPlan;
use ME\MerchandisingSfl\Models\TnaTask;

/**
 * Planning → T&A Calendar: the client's "Time & Action Calendar" sheet.
 * One row per milestone (T&A task) of each T&A plan, with status and delay days
 * worked out from the plan / revised / actual dates. Read-only — every value is
 * entered on the T&A page; Department, Dependency and Corrective Action have no
 * entry yet and print empty.
 */
class TnaCalendar
{
    /** Status filter / label / colour. Keys match TnaTask::state() except done + done_late = completed. */
    public const STATUSES = [
        'completed' => 'Completed',
        'at_risk' => 'Due / At Risk',
        'delayed' => 'Delayed',
        'pending' => 'Pending',
        'na' => 'N/A',
    ];

    /** TnaTask::state() => [label, colour class]. */
    private const STATE_VIEW = [
        'done' => ['Completed', 'green'],
        'done_late' => ['Completed (Late)', 'amber'],
        'delayed' => ['Delayed', 'red'],
        'due_soon' => ['At Risk', 'amber'],
        'pending' => ['Pending', ''],
        'na' => ['N/A', 'grey'],
    ];

    /** PO numbers per "order_id:style_id" of the plans on screen. */
    private array $pos = [];

    /** Plans matching the filters, each with only its matching tasks loaded. */
    public function plans(Request $r): Builder
    {
        $taskFilter = $this->taskFilter($r);

        return TnaPlan::query()
            ->with(['order.buyer', 'style', 'tasks' => fn ($q) => $taskFilter($q->with('responsible'))])
            ->whereIn('status', $r->boolean('closed') ? ['active', 'completed'] : ['active'])
            ->when($r->filled('buyer_id'), fn ($q) => $q->whereHas('order', fn ($q) => $q->where('buyer_id', $r->buyer_id)))
            ->when($r->filled('search'), function ($q) use ($r) {
                $like = '%' . $r->search . '%';
                $q->where(fn ($q) => $q->where('tna_no', 'like', $like)
                    ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', $like))
                    ->orWhereHas('order', fn ($q) => $q->where('order_no', 'like', $like))
                    ->orWhereExists(fn ($q) => $q->select(DB::raw(1))->from('msfl_order_pos as p')
                        ->whereColumn('p.order_id', 'msfl_tna_plans.order_id')->whereColumn('p.style_id', 'msfl_tna_plans.style_id')
                        ->where('p.po_no', 'like', $like)));
            })
            ->when($this->filtersTasks($r), fn ($q) => $q->whereHas('tasks', $taskFilter))
            ->orderBy('ex_factory_date')->orderBy('id');
    }

    /** Total / Completed / Due-At Risk / Delayed over every task of the filtered plans (not just one page). */
    public function summary(Request $r): array
    {
        $tasks = TnaTask::query()
            ->whereIn('tna_plan_id', $this->plans($r)->select('msfl_tna_plans.id'))
            ->tap(fn ($q) => $this->taskFilter($r)($q))
            ->get(['plan_date', 'revised_date', 'actual_date', 'is_na']);

        $states = $tasks->map(fn (TnaTask $t) => $t->state())->countBy();

        return [
            'Total Milestones' => $tasks->where('is_na', false)->count(),
            'Completed' => ($states['done'] ?? 0) + ($states['done_late'] ?? 0),
            'Due / At Risk' => $states['due_soon'] ?? 0,
            'Delayed' => $states['delayed'] ?? 0,
        ];
    }

    /** Load the PO numbers of these plans in one query. */
    public function prepare(Collection $plans): void
    {
        if ($plans->isEmpty()) {
            return;
        }

        $this->pos = DB::table('msfl_order_pos')
            ->whereIn('order_id', $plans->pluck('order_id')->unique())->whereIn('style_id', $plans->pluck('style_id')->unique())
            ->orderBy('id')->get(['order_id', 'style_id', 'po_no'])
            ->groupBy(fn ($p) => $p->order_id . ':' . $p->style_id)
            ->map(fn ($rows) => $rows->pluck('po_no')->unique()->implode(', '))
            ->all();
    }

    public function poNumbers(TnaPlan $plan): string
    {
        return $this->pos[$plan->order_id . ':' . $plan->style_id] ?? '';
    }

    /** [label, colour class] of a task's status. */
    public function status(TnaTask $task): array
    {
        return self::STATE_VIEW[$task->state()];
    }

    /** Calendar days late: done → actual − due; open → today − due; null when N/A or no date. */
    public function delayDays(TnaTask $task): ?int
    {
        $due = $task->dueDate();
        if ($task->is_na || ! $due) {
            return null;
        }

        $end = $task->actual_date ?? today();

        return max(0, (int) $due->diffInDays($end, false));
    }

    /** Applies the status and planned-date filters to a TnaTask query. */
    private function taskFilter(Request $r): Closure
    {
        $today = today()->toDateString();
        $soon = today()->addDays(TnaTask::DUE_SOON_DAYS)->toDateString();
        $due = 'COALESCE(revised_date, plan_date)';

        return fn ($q) => $q
            ->when($r->filled('from'), fn ($q) => $q->whereRaw("$due >= ?", [$r->from]))
            ->when($r->filled('to'), fn ($q) => $q->whereRaw("$due <= ?", [$r->to]))
            ->when($r->input('status'), fn ($q, $status) => match ($status) {
                'completed' => $q->whereNotNull('actual_date')->where('is_na', false),
                'delayed' => $q->whereNull('actual_date')->where('is_na', false)->whereRaw("$due < ?", [$today]),
                'at_risk' => $q->whereNull('actual_date')->where('is_na', false)->whereRaw("$due BETWEEN ? AND ?", [$today, $soon]),
                'pending' => $q->whereNull('actual_date')->where('is_na', false)->where(fn ($q) => $q->whereRaw("$due IS NULL")->orWhereRaw("$due > ?", [$soon])),
                'na' => $q->where('is_na', true),
                default => $q,
            });
    }

    private function filtersTasks(Request $r): bool
    {
        return $r->filled('status') || $r->filled('from') || $r->filled('to');
    }
}
