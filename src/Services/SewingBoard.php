<?php

namespace ME\MerchandisingSfl\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use ME\MerchandisingSfl\Models\Bulletin;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Production\SewingPlan;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Sewing board (Production → Sewing): one row per line × PO for a day —
 * plan (target, hours, SMV, operators, helpers), hourly output / reject /
 * rework, today / previous / grand output, DHU and efficiency.
 *
 *   DHU %        = (reject + rework) ÷ (output + reject + rework) × 100
 *   Work min     = (operators + helpers) × working hours × 60
 *   Produced min = today's output × SMV
 *   Efficiency % = produced min ÷ work min × 100
 *   Hourly       = the same per hour slot (an hour's work min = manpower × 60)
 *   Target eff % = target × SMV ÷ work min × 100
 *   Running day  = days the line has had sewing entries for the PO, up to this day
 *
 * Only line entries of the Sewing screen count (stage sewing, kind production).
 */
class SewingBoard
{
    /**
     * Plan figures to start a line's day on a PO with: the style's approved
     * bulletin (else its latest), falling back to the style SMV and the line's
     * own planning figures. A bulletin SMV of 0 never hides the style SMV.
     */
    public function defaults(OrderPo $po, ?Line $line): array
    {
        $bulletin = Bulletin::query()->where('style_id', $po->style_id)
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")->latest('id')->first();

        $smv = (float) ($bulletin->total_smv ?? 0) > 0 ? (float) $bulletin->total_smv : (float) ($po->style->smv ?? 0);
        $hours = (float) ($bulletin->working_hours ?? 0) ?: ($line && $line->working_minutes ? round($line->working_minutes / 60, 1) : 0);

        return [
            'smv' => round($smv, 3),
            'working_hours' => $hours,
            'operators' => (int) ($bulletin->operators ?? 0) ?: (int) ($line->operators ?? 0),
            'helpers' => (int) ($bulletin->helpers ?? 0) ?: (int) ($line->helpers ?? 0),
            'target' => (int) ($bulletin->target_per_day ?? 0) ?: ($line ? $line->dailyCapacity($smv) : 0),
        ];
    }

    /**
     * The day's rows: line × PO (planned or with entries), then the active
     * lines with nothing running. Each row: line, po, plan figures, hours
     * (slot => [out, rej, rw]), totals and the derived figures.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function rows(Carbon $date, ?int $lineId = null): array
    {
        $day = $date->toDateString();
        $lines = Lookups::lines()->when($lineId, fn ($c) => $c->where('id', $lineId))->values();
        $lineIds = $lines->pluck('id');

        $plans = SewingPlan::query()->whereDate('plan_date', $day)->whereIn('line_id', $lineIds)->get()
            ->keyBy(fn ($p) => $p->line_id . '|' . $p->order_po_id);

        $base = fn () => Entry::query()->where('stage', 'sewing')->where('kind', 'production')->whereIn('line_id', $lineIds);

        // line|po => hour => [out, rej, rw]
        $hourly = $base()->whereDate('entry_date', $day)->whereNotNull('hour_slot')
            ->selectRaw('line_id, order_po_id, hour_slot, SUM(pass_qty) o, SUM(reject_qty) rj, SUM(rework_qty) rw')
            ->groupBy('line_id', 'order_po_id', 'hour_slot')->get()
            ->groupBy(fn ($r) => $r->line_id . '|' . $r->order_po_id);
        // Today's totals also take entries without an hour (older day entries).
        $today = $base()->whereDate('entry_date', $day)
            ->selectRaw('line_id, order_po_id, SUM(input_qty) i, SUM(pass_qty) o, SUM(reject_qty) rj, SUM(rework_qty) rw')
            ->groupBy('line_id', 'order_po_id')->get()->keyBy(fn ($r) => $r->line_id . '|' . $r->order_po_id);
        $before = $base()->whereDate('entry_date', '<', $day)
            ->selectRaw('line_id, order_po_id, SUM(input_qty) i, SUM(pass_qty) o')
            ->groupBy('line_id', 'order_po_id')->get()->keyBy(fn ($r) => $r->line_id . '|' . $r->order_po_id);
        $life = $base()->whereDate('entry_date', '<=', $day)
            ->selectRaw('line_id, order_po_id, MIN(CASE WHEN input_qty > 0 THEN entry_date END) first_in, COUNT(DISTINCT entry_date) days')
            ->groupBy('line_id', 'order_po_id')->get()->keyBy(fn ($r) => $r->line_id . '|' . $r->order_po_id);

        $keys = $plans->keys()->merge($today->keys())->unique()->values();
        $pos = OrderPo::query()->with(['order:id,order_no,buyer_id,total_qty', 'order.buyer:id,name', 'style:id,style_no,name,smv', 'color:id,name'])
            ->whereIn('id', $keys->map(fn ($k) => (int) explode('|', $k)[1]))->get()->keyBy('id');
        // PO sewing output to date over every line — what is left to sew.
        $poSewn = Entry::query()->where('stage', 'sewing')->where('kind', 'production')->whereIn('order_po_id', $pos->keys())
            ->whereDate('entry_date', '<=', $day)->selectRaw('order_po_id, SUM(pass_qty) o')->groupBy('order_po_id')->pluck('o', 'order_po_id');

        $rows = [];
        foreach ($lines as $line) {
            $lineKeys = $keys->filter(fn ($k) => str_starts_with($k, $line->id . '|'));
            if ($lineKeys->isEmpty()) {
                $rows[] = ['line' => $line, 'idle' => true];
                continue;
            }
            foreach ($lineKeys as $key) {
                $po = $pos->get((int) explode('|', $key)[1]);
                if (! $po) {
                    continue;
                }
                $rows[] = $this->row($line, $po, $plans->get($key), $hourly->get($key, collect()), $today->get($key), $before->get($key), (int) ($poSewn[$po->id] ?? 0), $life->get($key));
            }
        }

        return ['rows' => $rows, 'totals' => $this->totals(collect($rows)->reject(fn ($r) => $r['idle'] ?? false))];
    }

    private function row(Line $line, OrderPo $po, ?SewingPlan $plan, Collection $hourly, $today, $before, int $poSewn, $life = null): array
    {
        $hours = [];
        foreach ($hourly as $h) {
            $hours[(int) $h->hour_slot] = ['out' => (int) $h->o, 'rej' => (int) $h->rj, 'rw' => (int) $h->rw];
        }
        $out = (int) ($today->o ?? 0);
        $rej = (int) ($today->rj ?? 0);
        $rw = (int) ($today->rw ?? 0);
        $prev = (int) ($before->o ?? 0);
        $smv = (float) ($plan->smv ?? 0);
        $manpower = (int) ($plan?->manpower() ?? 0);
        $workMin = (int) round($manpower * (float) ($plan->working_hours ?? 0) * 60);
        $prodMin = round($out * $smv, 2);
        $hourEff = [];
        $hourDhu = [];
        foreach ($hours as $h => $c) {
            $hourEff[$h] = $manpower > 0 ? round($c['out'] * $smv / ($manpower * 60) * 100, 2) : 0;
            $checked = $c['out'] + $c['rej'] + $c['rw'];
            $hourDhu[$h] = $checked > 0 ? round(($c['rej'] + $c['rw']) / $checked * 100, 2) : 0;
        }

        return [
            'line' => $line,
            'po' => $po,
            'plan' => $plan,
            'target' => (int) ($plan->target ?? 0),
            'working_hours' => (float) ($plan->working_hours ?? 0),
            'smv' => $smv,
            'operators' => (int) ($plan->operators ?? 0),
            'helpers' => (int) ($plan->helpers ?? 0),
            'manpower' => $manpower,
            'input' => (int) ($before->i ?? 0) + (int) ($today->i ?? 0),
            'today_input' => (int) ($today->i ?? 0),
            'hours' => $hours,
            'out' => $out,
            'rej' => $rej,
            'rw' => $rw,
            'dhu' => ($out + $rej + $rw) > 0 ? round(($rej + $rw) / ($out + $rej + $rw) * 100, 2) : 0,
            'previous' => $prev,
            'grand' => $prev + $out,
            'balance' => max(0, (int) $po->po_qty - $poSewn),
            'work_min' => $workMin,
            'prod_min' => $prodMin,
            'efficiency' => $workMin > 0 ? round($prodMin / $workMin * 100, 2) : 0,
            'hour_eff' => $hourEff,
            'hour_dhu' => $hourDhu,
            'target_eff' => $workMin > 0 ? round((int) ($plan->target ?? 0) * $smv / $workMin * 100, 2) : 0,
            'input_start' => $life?->first_in ? Carbon::parse($life->first_in) : null,
            'running_day' => (int) ($life->days ?? 0),
            'remarks' => $plan->remarks ?? null,
        ];
    }

    private function totals(Collection $rows): array
    {
        $hours = [];
        $hourEff = [];
        $hourDhu = [];
        foreach (array_keys(ProductionFlow::hourSlots()) as $h) {
            $hours[$h] = (int) $rows->sum(fn ($r) => $r['hours'][$h]['out'] ?? 0);
            // Lines that worked this hour: their minutes produced ÷ their minutes available.
            $worked = $rows->filter(fn ($r) => isset($r['hours'][$h]));
            $hourMin = $worked->sum(fn ($r) => $r['manpower'] * 60);
            $hourEff[$h] = $hourMin > 0 ? round($worked->sum(fn ($r) => $r['hours'][$h]['out'] * $r['smv']) / $hourMin * 100, 2) : 0;
            $bad = $worked->sum(fn ($r) => $r['hours'][$h]['rej'] + $r['hours'][$h]['rw']);
            $checked = $worked->sum(fn ($r) => $r['hours'][$h]['out']) + $bad;
            $hourDhu[$h] = $checked > 0 ? round($bad / $checked * 100, 2) : 0;
        }
        $sum = fn ($k) => $rows->sum($k);
        $checked = $sum('out') + $sum('rej') + $sum('rw');

        return [
            'lines' => $rows->pluck('line.id')->unique()->count(),
            'styles' => $rows->pluck('po.style_id')->unique()->count(),
            'target' => (int) $sum('target'),
            'operators' => (int) $sum('operators'),
            'helpers' => (int) $sum('helpers'),
            'manpower' => (int) $sum('manpower'),
            'input' => (int) $sum('input'),
            'hours' => $hours,
            'out' => (int) $sum('out'),
            'rej' => (int) $sum('rej'),
            'rw' => (int) $sum('rw'),
            'dhu' => $checked > 0 ? round(($sum('rej') + $sum('rw')) / $checked * 100, 2) : 0,
            'previous' => (int) $sum('previous'),
            'grand' => (int) $sum('grand'),
            'work_min' => (int) $sum('work_min'),
            'prod_min' => round($sum('prod_min'), 2),
            'efficiency' => $sum('work_min') > 0 ? round($sum('prod_min') / $sum('work_min') * 100, 2) : 0,
            'hour_eff' => $hourEff,
            'hour_dhu' => $hourDhu,
            'target_eff' => $sum('work_min') > 0 ? round($rows->sum(fn ($r) => $r['target'] * $r['smv']) / $sum('work_min') * 100, 2) : 0,
            'smv' => $sum('out') > 0 ? round($rows->sum(fn ($r) => $r['out'] * $r['smv']) / $sum('out'), 2) : round((float) $rows->avg('smv'), 2),
            'hourly_target' => (int) $rows->sum(fn ($r) => $r['working_hours'] > 0 ? (int) round($r['target'] / $r['working_hours']) : 0),
        ];
    }
}
