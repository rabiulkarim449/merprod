<?php

namespace ME\MerchandisingSfl\Services;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\Production\SewingPlan;
use ME\MerchandisingSfl\Models\TnaPlan;

/**
 * Planning → Capacity Calendar: the client's "Factory Capacity Per Day Calendar" — one row per
 * line per day, worked out from what the system already holds:
 *   - working day        weekly off + holidays (WorkingCalendar)
 *   - operators, helpers, regular hours, efficiency   the line set-up (Planning → Setup → Lines)
 *   - product, SMV, hours, manpower, remarks          that day's sewing plan (Production → Sewing board);
 *                                                     else the T&A whose sewing window covers the day
 *   - machines            the line's machines (Inventory, else Lines set-up)
 *   - OT hours            sewing-plan hours above the line's regular hours
 * Same sums as the sheet: Available Minutes = operators × (regular + OT hours) × 60,
 * Gross = minutes ÷ SMV, Net = minutes × efficiency ÷ SMV. Shift is not kept anywhere and prints empty.
 */
class CapacityCalendar
{
    public const MAX_DAYS = 62;

    public function __construct(private readonly WorkingCalendar $calendar)
    {
    }

    /** [from, to] — default today + 13 days, at most MAX_DAYS. */
    public function period(Request $r): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from)->startOfDay() : today();
        $to = $r->filled('to') ? Carbon::parse($r->to)->startOfDay() : $from->copy()->addDays(13);
        if ($to->lt($from)) {
            $to = $from->copy();
        }
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $to = $from->copy()->addDays(self::MAX_DAYS - 1);
        }

        return [$from, $to];
    }

    public function lines(): Collection
    {
        return Line::query()->with(['floorLine', 'machines'])->where('is_active', true)->get()->sortBy(fn (Line $l) => $l->name)->values();
    }

    /** One array per line per day (off days only with ?off=1). */
    public function rows(Request $r): Collection
    {
        [$from, $to] = $this->period($r);
        $lines = $this->lines()->when($r->filled('line_id'), fn ($c) => $c->where('id', (int) $r->line_id));
        if ($lines->isEmpty()) {
            return collect();
        }
        $lineIds = $lines->pluck('id');

        $sewing = SewingPlan::query()->with('orderPo.style')->whereIn('line_id', $lineIds)
            ->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])->get()
            ->groupBy(fn ($p) => $p->line_id . '|' . Carbon::parse($p->plan_date)->toDateString());

        // T&A plans sewing on these lines within the period.
        $tna = TnaPlan::query()->with(['style', 'lines'])->where('status', 'active')
            ->whereNotNull('sewing_start_date')->whereNotNull('sewing_end_date')
            ->whereDate('sewing_start_date', '<=', $to)->whereDate('sewing_end_date', '>=', $from)
            ->whereHas('lines', fn ($q) => $q->whereIn('msfl_lines.id', $lineIds))->get();

        $machines = $lines->mapWithKeys(function (Line $l) {
            try {
                return [$l->id => $l->totalMachines()];
            } catch (\Throwable) {
                return [$l->id => null];
            }
        });
        $weeklyOff = config('merchandising-sfl.weekly_off_days', [5]);

        $rows = collect();
        foreach (CarbonPeriod::create($from, $to) as $period) {
            $day = Carbon::instance($period);
            $working = $this->calendar->isWorkingDay($day);
            if (! $working && ! $r->boolean('off')) {
                continue;
            }
            foreach ($lines as $line) {
                $rows->push($this->row($line, $day->copy(), $working, $sewing->get($line->id . '|' . $day->toDateString()), $tna, $machines[$line->id], $weeklyOff));
            }
        }

        return $rows;
    }

    /** Calendar Capacity / Working Entries / Avg Efficiency / Avg Net per line-day. */
    public function summary(Collection $rows): array
    {
        $working = $rows->where('working', true);
        $capacity = (int) $working->sum('net');

        return [
            'Calendar Capacity' => number_format($capacity),
            'Working Entries' => number_format($working->count()),
            'Avg Efficiency' => $working->isEmpty() ? '0.0%' : number_format($working->avg('efficiency'), 1) . '%',
            'Avg Net / Line-Day' => $working->isEmpty() ? '0' : number_format($capacity / $working->count()),
        ];
    }

    private function row(Line $line, Carbon $day, bool $working, ?Collection $plans, Collection $tna, ?int $machines, array $weeklyOff): array
    {
        $regularLine = round((int) $line->working_minutes / 60, 2);
        $operators = (int) $line->operators;
        $helpers = (int) $line->helpers;
        $hours = null;
        $smv = null;
        $product = '';
        $remarks = [];
        $source = '';

        if ($plans && $plans->isNotEmpty()) {
            // Sewing board for that day; several POs on one line → SMV weighted by target.
            $operators = (int) $plans->max('operators') ?: $operators;
            $helpers = (int) $plans->max('helpers') ?: $helpers;
            $hours = (float) $plans->max('working_hours') ?: null;
            $withSmv = $plans->filter(fn ($p) => (float) $p->smv > 0);
            $weight = $withSmv->sum(fn ($p) => max(1, (int) $p->target));
            $smv = $withSmv->isNotEmpty() ? $withSmv->sum(fn ($p) => (float) $p->smv * max(1, (int) $p->target)) / $weight : null;
            // No SMV on the plan → the styles' SMV.
            $smv ??= ($plans->map(fn ($p) => (float) ($p->orderPo->style->smv ?? 0))->filter()->avg() ?: null);
            $product = $plans->map(fn ($p) => $p->orderPo->style->style_no ?? null)->filter()->unique()->implode(', ');
            $remarks = $plans->pluck('remarks')->filter()->unique()->all();
            $source = 'Sewing plan';
        }

        $running = $tna->filter(fn (TnaPlan $p) => $p->lines->contains('id', $line->id)
            && $day->betweenIncluded($p->sewing_start_date, $p->sewing_end_date));
        if ($smv === null && $running->isNotEmpty() && $source !== '') {
            // Sewing plan without any SMV → the T&A's.
            $smv = (float) $running->avg(fn ($p) => (float) $p->smv) ?: null;
        } elseif ($source === '') {
            if ($running->isNotEmpty()) {
                $smv = (float) $running->avg(fn ($p) => (float) $p->smv) ?: null;
                $product = $running->map(fn ($p) => $p->style->style_no ?? null)->filter()->unique()->implode(', ');
                $source = 'T&A ' . $running->pluck('tna_no')->implode(', ');
            }
        }

        $regular = $hours !== null ? min($hours, $regularLine) : $regularLine;
        $ot = $hours !== null ? max(0, round($hours - $regularLine, 2)) : 0;
        $minutes = $working ? (int) round($operators * ($regular + $ot) * 60) : 0;
        $efficiency = (float) $line->efficiency_percent;

        if (! $working) {
            $remarks[] = in_array($day->dayOfWeek, $weeklyOff, true) ? 'Weekly off' : 'Holiday';
        }

        return [
            'date' => $day,
            'working' => $working,
            'shift' => '',
            'line' => $line->name,
            'product' => $product,
            'machines' => $machines,
            'operators' => $operators,
            'helpers' => $helpers,
            'regular' => $working ? $regular : 0,
            'ot' => $working ? $ot : 0,
            'minutes' => $minutes,
            'efficiency' => $efficiency,
            'smv' => $smv ? round($smv, 2) : null,
            'gross' => $smv && $working ? (int) round($minutes / $smv) : null,
            'net' => $smv && $working ? (int) round($minutes * $efficiency / 100 / $smv) : null,
            'remarks' => implode('; ', $remarks),
            'source' => $source,
        ];
    }
}
