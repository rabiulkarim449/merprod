<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use ME\MerchandisingSfl\Models\Bom;
use ME\MerchandisingSfl\Models\Bulletin;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Models\TnaPlan;

/**
 * Works out a T&A backwards from the shipment date (working days only):
 *
 *   daily capacity = Σ lines (operators × minutes × efficiency ÷ SMV)
 *   sewing days    = ⌈ order qty ÷ daily capacity ⌉
 *   ex-factory     = shipment     − template.ship_to_ex_factory_days
 *   sewing end     = ex-factory   − template.ex_factory_to_sewing_end_days
 *   sewing start   = sewing end   − (sewing days − 1)
 *   PCD            = sewing start − template.pcd_to_sewing_start_days
 *   every step     = its anchor (one of the dates above, or order confirmation) ± its offset
 */
class TnaPlanner
{
    public function __construct(private readonly WorkingCalendar $calendar)
    {
    }

    /**
     * Inputs a new T&A takes from the order: the style's total qty, its
     * earliest PO shipment date, and the SMV (latest approved bulletin, else the style's SMV).
     *
     * @return array{order_qty: int, shipment_date: ?Carbon, smv: ?float, bulletin_id: ?int}
     */
    public function inputsFromOrder(Order $order, Style $style): array
    {
        $bulletin = $this->bulletinFor($style);

        return [
            'order_qty' => $order->styleQty($style->id),
            'shipment_date' => ($date = $order->pos()->where('style_id', $style->id)->min('shipment_date')) ? Carbon::parse($date) : null,
            // A bulletin with no active operations (SMV 0) mustn't hide the style's own SMV.
            'smv' => $bulletin && (float) $bulletin->total_smv > 0 ? (float) $bulletin->total_smv : ($style->smv !== null ? (float) $style->smv : null),
            'bulletin_id' => $bulletin?->id,
        ];
    }

    public function bulletinFor(Style $style): ?Bulletin
    {
        return Bulletin::query()->where('style_id', $style->id)
            ->orderByRaw("status = 'approved' desc")->orderByDesc('version')
            ->first();
    }

    /** Capacity, sewing days and milestone dates. Saves the plan and each line's capacity share. */
    public function calculate(TnaPlan $plan, Collection $lines): void
    {
        $template = $plan->template;
        $smv = (float) $plan->smv;

        $shares = $lines->mapWithKeys(fn ($line) => [$line->id => ['daily_capacity' => $line->dailyCapacity($smv)]]);
        $capacity = (int) $shares->sum('daily_capacity');
        $sewingDays = $capacity > 0 ? (int) ceil($plan->order_qty / $capacity) : 0;

        $exFactory = $this->calendar->shift($plan->shipment_date, -$template->ship_to_ex_factory_days);
        $sewingEnd = $this->calendar->shift($exFactory, -$template->ex_factory_to_sewing_end_days);
        $sewingStart = $this->calendar->shift($sewingEnd, -max($sewingDays - 1, 0));
        $pcd = $this->calendar->shift($sewingStart, -$template->pcd_to_sewing_start_days);

        $plan->forceFill([
            'daily_capacity' => $capacity,
            'sewing_days' => $sewingDays,
            'ex_factory_date' => $exFactory,
            'sewing_end_date' => $sewingEnd,
            'sewing_start_date' => $sewingStart,
            'pcd_date' => $pcd,
            'is_feasible' => $capacity > 0 && $pcd->gte(today()),
            'calculated_at' => now(),
        ])->save();

        $plan->lines()->sync($shares->all());
    }

    /** Create the T&A steps from the template (first time only). */
    public function generateTasks(TnaPlan $plan): void
    {
        $plan->loadMissing(['template.tasks', 'style', 'order']);

        foreach ($plan->template->tasks as $step) {
            $plan->tasks()->create([
                'tna_template_task_id' => $step->id,
                'sequence' => $step->sequence,
                'group_name' => $step->group_name,
                'task_code' => $step->task_code,
                'task_name' => $step->task_name,
                'anchor' => $step->anchor,
                'offset_days' => $step->offset_days,
                'auto_source' => $step->auto_source,
                'is_mandatory' => $step->is_mandatory,
                'plan_date' => $this->planDate($plan, $step->anchor, $step->offset_days),
                'is_na' => $step->condition === 'wash' && ! $plan->style->wash_type_id,
            ]);
        }

        $this->syncAutoActuals($plan);
    }

    /**
     * Re-plan after the order / lines changed: refresh qty, shipment and SMV
     * from the order, recalculate, and move the planned date of every step
     * that is not done yet. Done steps and revised dates are kept.
     */
    public function recalculate(TnaPlan $plan, ?Collection $lines = null): void
    {
        $plan->loadMissing(['order', 'style', 'template', 'lines']);
        $inputs = $this->inputsFromOrder($plan->order, $plan->style);

        $plan->order_qty = $inputs['order_qty'] ?: $plan->order_qty;
        $plan->shipment_date = $inputs['shipment_date'] ?? $plan->shipment_date;
        if ($inputs['smv']) {
            $plan->smv = $inputs['smv'];
            $plan->bulletin_id = $inputs['bulletin_id'];
        }

        $this->calculate($plan, $lines ?? $plan->lines);

        foreach ($plan->tasks()->whereNull('actual_date')->get() as $task) {
            $task->update(['plan_date' => $this->planDate($plan, $task->anchor, $task->offset_days)]);
        }

        $this->syncAutoActuals($plan);
    }

    /**
     * Fill actual dates that the system already knows: order confirmed, BOM /
     * bulletin approved, samples, fabric in-house (Inventory) and production
     * milestones (cutting / sewing / … / packing of this order + style).
     */
    public function syncAutoActuals(TnaPlan $plan): void
    {
        $plan->loadMissing('order');

        foreach ($plan->tasks()->whereNull('actual_date')->where('auto_source', '!=', 'none')->get() as $task) {
            if ($date = $this->autoActualDate($plan, $task->auto_source)) {
                $task->update(['actual_date' => $date]);
            }
        }
    }

    private function autoActualDate(TnaPlan $plan, string $source): ?Carbon
    {
        [$kind, $code] = array_pad(explode(':', $source, 2), 2, null);

        $date = match ($kind) {
            'order_confirmed' => $plan->order->confirmed_at,
            'bom_approved' => Bom::query()->where('style_id', $plan->style_id)
                ->where(fn ($q) => $q->where('order_id', $plan->order_id)->orWhereNull('order_id'))
                ->where('status', 'approved')->min('approved_at'),
            'bulletin_approved' => Bulletin::query()->where('style_id', $plan->style_id)->where('status', 'approved')->min('approved_at'),
            'sample_submitted' => Sample::query()->where('style_id', $plan->style_id)
                ->whereHas('sampleType', fn ($q) => $q->where('code', $code))->min('submit_date'),
            'sample_approved' => Sample::query()->where('style_id', $plan->style_id)->where('status', 'approved')
                ->whereHas('sampleType', fn ($q) => $q->where('code', $code))->min('decision_date'),
            'fabric_inhouse' => class_exists(\ME\SflInventory\Models\InvGrn::class)
                ? \ME\SflInventory\Models\InvGrn::query()->where('source_type', 'buyer_supplied')->where('status', 'posted')
                    ->where('msfl_style_id', $plan->style_id)->min('receive_date')
                : null,
            'cutting_started' => Production\Cutting::query()->whereIn('order_po_id', $this->planPoIds($plan))->min('cutting_date'),
            'sewing_started' => Production\Entry::query()->whereIn('order_po_id', $this->planPoIds($plan))
                ->where('stage', 'sewing')->where('input_qty', '>', 0)->min('entry_date'),
            'sewing_done', 'washing_done', 'finishing_done', 'final_qc_done', 'packing_done' => $this->stageCompletedOn($plan, substr($kind, 0, -5)),
            default => null,
        };

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    /** @return list<int> the order's PO lines of this plan's style */
    private function planPoIds(TnaPlan $plan): array
    {
        return OrderPo::query()->where('order_id', $plan->order_id)->where('style_id', $plan->style_id)->pluck('id')->all();
    }

    /**
     * A stage is complete once every piece cut for this order + style has been
     * through it: the full order qty is cut, and nothing is waiting for or
     * sitting in this stage or any stage before it (rejects leave the flow,
     * rework stays in WIP). Date = the stage's last entry. Null while open,
     * or when no PO of the plan goes through the stage (e.g. no washing).
     */
    private function stageCompletedOn(TnaPlan $plan, string $stage): ?string
    {
        $flow = app(ProductionFlow::class);
        $pos = OrderPo::query()->where('order_id', $plan->order_id)->where('style_id', $plan->style_id)->get();
        $cut = 0;
        $onRoute = 0;

        foreach ($pos as $po) {
            $summary = $flow->summary($po);
            $cut += $summary['cutting']['pass'];
            if (! isset($summary[$stage])) {
                continue;
            }
            $onRoute++;
            foreach ($summary as $s => $row) {
                if ($s !== 'cutting' && ($row['wip'] > 0 || $row['available'] > 0)) {
                    return null; // still pieces upstream or in this stage
                }
                if ($s === $stage) {
                    break;
                }
            }
        }

        if ($onRoute === 0 || $cut < $plan->order_qty) {
            return null;
        }

        return Production\Entry::query()->whereIn('order_po_id', $pos->pluck('id'))->where('stage', $stage)->max('entry_date');
    }

    public function anchorDate(TnaPlan $plan, string $anchor): ?Carbon
    {
        return match ($anchor) {
            'order_confirm' => ($plan->order->confirmed_at ?? $plan->order->order_date)?->copy()->startOfDay(),
            'pcd' => $plan->pcd_date,
            'sewing_start' => $plan->sewing_start_date,
            'sewing_end' => $plan->sewing_end_date,
            'ex_factory' => $plan->ex_factory_date,
            'shipment' => $plan->shipment_date,
            default => null,
        };
    }

    private function planDate(TnaPlan $plan, string $anchor, int $offset): ?Carbon
    {
        $anchorDate = $this->anchorDate($plan, $anchor);

        return $anchorDate ? $this->calendar->shift($anchorDate, $offset) : null;
    }

    /**
     * When the plan is not feasible: working days still available for sewing
     * (cutting can start no earlier than today) and the daily output that would need.
     *
     * @return array{available_days: int, required_per_day: int, shortfall_per_day: int}
     */
    public function shortfall(TnaPlan $plan): array
    {
        $earliestSewingStart = $this->calendar->shift(today(), $plan->template->pcd_to_sewing_start_days);
        $available = $plan->sewing_end_date ? $this->calendar->workingDaysBetween($earliestSewingStart, $plan->sewing_end_date) : 0;
        $required = $available > 0 ? (int) ceil($plan->order_qty / $available) : $plan->order_qty;

        return [
            'available_days' => $available,
            'required_per_day' => $required,
            'shortfall_per_day' => max(0, $required - $plan->daily_capacity),
        ];
    }

    /**
     * Other active T&As whose sewing window overlaps this one on a shared line.
     *
     * @return Collection<int, array{line: string, plan: TnaPlan}>
     */
    public function conflicts(TnaPlan $plan): Collection
    {
        if (! $plan->sewing_start_date || ! $plan->sewing_end_date) {
            return collect();
        }

        $plan->loadMissing('lines');

        return TnaPlan::query()
            ->with(['lines', 'style', 'order'])
            ->whereKeyNot($plan->id)
            ->where('status', 'active')
            ->whereHas('lines', fn ($q) => $q->whereIn('msfl_lines.id', $plan->lines->pluck('id')))
            ->whereDate('sewing_start_date', '<=', $plan->sewing_end_date)
            ->whereDate('sewing_end_date', '>=', $plan->sewing_start_date)
            ->get()
            ->flatMap(fn (TnaPlan $other) => $other->lines->whereIn('id', $plan->lines->pluck('id'))
                ->map(fn ($line) => ['line' => $line->name, 'plan' => $other]))
            ->values();
    }
}
