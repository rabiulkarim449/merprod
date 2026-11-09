<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Inquiry;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Cutting;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\TnaTask;

/** Figures for the Merchandising v2 dashboard and the home-page widget — a handful of grouped queries. */
class DashboardStats
{
    public static function get(): array
    {
        $today = today();
        $confirmedPos = OrderPo::query()->whereHas('order', fn ($q) => $q->where('status', 'confirmed'));

        // Pieces at each stage across running orders: WIP = in − pass − reject.
        $stageSums = Entry::query()->whereIn('order_po_id', (clone $confirmedPos)->select('id'))
            ->selectRaw("stage, SUM(input_qty) i, SUM(CASE WHEN kind = 'qc' THEN 0 ELSE pass_qty END) p,
                SUM(CASE WHEN kind = 'qc' THEN 0 ELSE reject_qty END) rj, SUM(CASE WHEN kind IN ('qc', 'rework') THEN rework_qty ELSE 0 END) qrw")
            ->groupBy('stage')->get()->keyBy('stage');

        $last30 = Entry::query()->whereDate('entry_date', '>=', $today->copy()->subDays(29))
            ->selectRaw('stage, SUM(pass_qty) p, SUM(reject_qty) rj, SUM(rework_qty) rw')->groupBy('stage')->get()->keyBy('stage');

        $todayByStage = Entry::query()->whereDate('entry_date', $today)
            ->selectRaw("stage, SUM(input_qty) i, SUM(" . ProductionFlow::NET_PASS_SQL . ") p, SUM(reject_qty) rj")->groupBy('stage')->get()->keyBy('stage');

        $stages = ['cutting' => 'Cutting'] + collect(ProductionFlow::STAGES)->map(fn ($s) => $s[0])->all();
        $production = [];
        foreach ($stages as $stage => $label) {
            $s = $stageSums->get($stage);
            $l = $last30->get($stage);
            if ($stage === 'cutting') {
                // Everything cut counts as passed; what is waiting is the rework found at cutting.
                $cut30 = (int) Cutting::query()->whereDate('cutting_date', '>=', $today->copy()->subDays(29))->sum('total_qty');
                $production[$stage] = [
                    'label' => $label,
                    'today' => (int) Cutting::query()->whereDate('cutting_date', $today)->sum('total_qty'),
                    'wip' => max(0, (int) ($s->qrw ?? 0) - (int) ($s->p ?? 0) - (int) ($s->rj ?? 0)),
                    'reject_pct' => $cut30 > 0 ? round((int) ($l->rj ?? 0) / $cut30 * 100, 1) : null,
                ];
                continue;
            }
            $checked = (int) ($l->p ?? 0) + (int) ($l->rj ?? 0);
            $production[$stage] = [
                'label' => $label,
                'today' => (int) ($todayByStage->get($stage)->p ?? 0),
                'wip' => max(0, (int) ($s->i ?? 0) - (int) ($s->p ?? 0) - (int) ($s->rj ?? 0) + (int) ($s->qrw ?? 0)),
                'reject_pct' => $checked > 0 ? round((int) $l->rj / $checked * 100, 1) : null,
            ];
        }

        // Output trend (sewing pass / packed per day).
        $trend = Entry::query()->whereDate('entry_date', '>=', $today->copy()->subDays(13))->whereIn('stage', ['sewing', 'packing'])
            ->selectRaw("DATE(entry_date) d, stage, SUM(" . ProductionFlow::NET_PASS_SQL . ") p")->groupBy('d', 'stage')->get();
        $days = collect(range(13, 0))->mapWithKeys(fn ($n) => [$today->copy()->subDays($n)->toDateString() => ['sewing' => 0, 'packing' => 0]])->all();
        foreach ($trend as $t) {
            $days[$t->d][$t->stage] = (int) $t->p;
        }

        // Shipments due in 30 days with packed progress.
        $due = (clone $confirmedPos)->with(['order:id,order_no,buyer_id', 'order.buyer:id,name', 'style:id,style_no'])
            ->whereBetween('shipment_date', [$today->copy()->subDays(7), $today->copy()->addDays(30)])
            ->orderBy('shipment_date')->limit(10)->get();
        $packed = Entry::query()->where('stage', 'packing')->whereIn('order_po_id', $due->pluck('id'))
            ->selectRaw("order_po_id, SUM(" . ProductionFlow::NET_PASS_SQL . ") p")->groupBy('order_po_id')->pluck('p', 'order_po_id');
        $shipments = $due->map(fn (OrderPo $po) => [
            'po' => $po, 'packed' => (int) ($packed[$po->id] ?? 0),
            'pct' => $po->po_qty > 0 ? min(100, round((int) ($packed[$po->id] ?? 0) / $po->po_qty * 100)) : 0,
            'days' => (int) $today->diffInDays($po->shipment_date, false),
        ]);

        // Overdue T&A tasks of active plans.
        $overdueQuery = TnaTask::query()->whereNull('actual_date')->where('is_na', false)
            ->whereHas('plan', fn ($q) => $q->where('status', 'active'))
            ->whereRaw('COALESCE(revised_date, plan_date) < ?', [$today->toDateString()]);
        $overdue = (clone $overdueQuery)->with(['plan:id,tna_no,order_id,style_id', 'plan.style:id,style_no', 'responsible:id,name'])
            ->orderByRaw('COALESCE(revised_date, plan_date)')->limit(8)->get();

        $defects = DB::table('msfl_prod_entry_defects as d')->join('msfl_prod_entries as e', 'e.id', '=', 'd.entry_id')
            ->whereNull('e.deleted_at')->whereDate('e.entry_date', '>=', $today->copy()->subDays(29))
            ->selectRaw("e.stage, COALESCE(NULLIF(d.part_name, ''), '—') part, d.type, SUM(d.qty) qty")
            ->groupBy('e.stage', 'part', 'd.type')->orderByDesc('qty')->limit(6)->get();

        return [
            'open_inquiries' => Inquiry::query()->whereIn('status', ['open', 'quoted'])->count(),
            'running_orders' => Order::query()->where('status', 'confirmed')->count(),
            'running_qty' => (int) (clone $confirmedPos)->sum('po_qty'),
            'running_value' => (float) (clone $confirmedPos)->sum('total_value'),
            'samples_pending' => Sample::query()->whereIn('status', ['requested', 'in_progress', 'submitted'])->count(),
            'samples_overdue' => Sample::query()->whereIn('status', ['requested', 'in_progress'])->whereDate('required_date', '<', $today)->count(),
            'overdue_tasks' => (clone $overdueQuery)->count(),
            'packed_today' => (int) ($todayByStage->get('packing')->p ?? 0),
            'production' => $production,
            'trend' => $days,
            'shipments' => $shipments,
            'overdue' => $overdue,
            'defects' => $defects,
        ];
    }
}
