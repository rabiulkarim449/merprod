<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\ProductionFlow;

/** Daily CM & Profit — one row per sewing line × PO for the day. */
class CmProfit extends OpsReport
{
    public function key(): string
    {
        return 'cm-profit';
    }

    public function title(): string
    {
        return 'Daily Line CM & Profit Report';
    }

    public function department(): string
    {
        return 'Production / Accounts';
    }

    public function guidance(): string
    {
        return 'Good output (sewing pass), operators, helpers and hours (sewing plan) come from Production; selling CM / pc from the style / cost sheet (per dozen ÷ 12). Direct labour, OT cost and allocated overhead work out from the rates above (overhead is shared between a line\'s POs by output) — type a figure to override. Currency conversion, manufacturing cost and profit / loss are automatic.';
    }

    public function filters(): array
    {
        return ['buyer', 'line'];
    }

    public function settingsShown(): array
    {
        return ['bdt_usd_rate', 'target_margin', 'operator_rate', 'helper_rate', 'ot_rate', 'oh_per_line_day'];
    }

    public function columns(): array
    {
        return [
            self::col('date', 'Date', 'auto', 'date'), self::col('line', 'Line'), self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'),
            self::col('good_output', 'Good Output', 'auto', 'int'),
            self::col('cm_pc', 'Selling CM/Pc (USD)', 'auto_input', 'money'),
            self::col('cm_earned', 'CM Earned (USD)', 'formula', 'money'),
            self::col('operators', 'Operators', 'auto_input', 'int'), self::col('helpers', 'Helpers', 'auto_input', 'int'),
            self::col('working_hours', 'Working Hours', 'auto_input', 'num', ['max' => 24]), self::col('ot_hours', 'OT Hours', 'input', 'num', ['max' => 24]),
            self::col('direct_labour', 'Direct Labour (BDT)', 'auto_input', 'money'), self::col('ot_cost', 'OT Cost (BDT)', 'auto_input', 'money'),
            self::col('allocated_oh', 'Allocated OH (BDT)', 'auto_input', 'money'),
            self::col('rework_cost', 'Rework Cost (BDT)', 'input', 'money'), self::col('reject_cost', 'Reject Cost (BDT)', 'input', 'money'),
            self::col('other_cost', 'Other Cost (BDT)', 'input', 'money'),
            self::col('total_mfg', 'Total Mfg. Cost (BDT)', 'formula', 'money'), self::col('rate', 'Exchange Rate', 'formula', 'num'),
            self::col('mfg_usd', 'Mfg. Cost (USD)', 'formula', 'money'), self::col('mfg_pc', 'Mfg. Cost/Pc (USD)', 'formula', 'money'),
            self::col('pl', 'Profit / Loss (USD)', 'formula', 'money'), self::col('margin', 'Profit Margin %', 'formula', 'pct'),
            self::col('target', 'Target Margin %', 'formula', 'pct'), self::col('variance', 'Margin Variance', 'formula', 'pct'),
            self::col('status', 'Status', 'formula', 'text', ['status' => true]),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $d = $date->toDateString();
        $out = DB::table('msfl_prod_entries')->whereNull('deleted_at')->where('stage', 'sewing')->where('entry_date', $d)->whereNotNull('line_id')
            ->when($r->filled('line_id'), fn ($q) => $q->where('line_id', $r->line_id))
            ->selectRaw('line_id, order_po_id, SUM(' . ProductionFlow::NET_PASS_SQL . ') q')->groupBy('line_id', 'order_po_id')->get()
            ->keyBy(fn ($x) => $x->line_id . '|' . $x->order_po_id);
        $plans = DB::table('msfl_prod_sewing_plans')->where('plan_date', $d)->when($r->filled('line_id'), fn ($q) => $q->where('line_id', $r->line_id))
            ->get()->keyBy(fn ($x) => $x->line_id . '|' . $x->order_po_id);
        $keys = $out->keys()->merge($plans->keys())->unique();

        $pos = OrderPo::query()->with(['order.buyer', 'style.productType', 'color'])->whereIn('id', $keys->map(fn ($k) => (int) explode('|', $k)[1])->unique())
            ->whereHas('order', fn ($q) => $q->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id)))->get()->keyBy('id');
        $lines = Line::query()->with('floorLine')->get()->keyBy('id');
        $cm = $this->cmPerPiece($pos->pluck('style_id')->unique()->all());

        $rows = [];
        foreach ($keys as $k) {
            [$lineId, $poId] = array_map('intval', explode('|', $k));
            if (! ($po = $pos[$poId] ?? null)) {
                continue;
            }
            $p = $plans[$k] ?? null;
            $rows[] = [
                '_key' => 'd:' . $d . '|l:' . $lineId . '|p:' . $poId, '_label' => ($lines[$lineId]->name ?? 'Line') . ' · PO ' . $po->po_no,
                '_line_id' => $lineId,
                'date' => $d, 'line' => $lines[$lineId]->name ?? '', 'buyer' => $po->order->buyer->name ?? '', 'style_no' => $po->style->style_no ?? '', 'po_no' => $po->po_no,
                'good_output' => (int) ($out[$k]->q ?? 0),
                'cm_pc' => $cm[$po->style_id]['cm'] ?? null,
                'operators' => $p ? (int) $p->operators : null, 'helpers' => $p ? (int) $p->helpers : null,
                'working_hours' => $p ? (float) $p->working_hours : null,
            ];
        }

        return collect($rows)->sortBy(fn ($x) => $x['line'] . '|' . $x['po_no'])->values()->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        $lineOut = collect($rows)->groupBy('_line_id')->map(fn ($g) => ['out' => $g->sum('good_output'), 'n' => $g->count()]);
        $rate = (float) ($settings['bdt_usd_rate'] ?: 0);
        $auto = fn ($x, $f) => ($x['_manual'][$f] ?? null) === null;

        foreach ($rows as &$x) {
            $ops = (int) $x['operators'];
            $hlp = (int) $x['helpers'];
            if ($auto($x, 'direct_labour')) {
                $x['direct_labour'] = $settings['operator_rate'] !== null && $settings['helper_rate'] !== null && $x['working_hours']
                    ? ($ops * $settings['operator_rate'] + $hlp * $settings['helper_rate']) * $x['working_hours'] : null;
            }
            if ($auto($x, 'ot_cost')) {
                $x['ot_cost'] = $settings['ot_rate'] !== null && $x['ot_hours'] ? $x['ot_hours'] * ($ops + $hlp) * $settings['ot_rate'] : null;
            }
            if ($auto($x, 'allocated_oh')) {
                $l = $lineOut[$x['_line_id']];
                $x['allocated_oh'] = $settings['oh_per_line_day'] !== null
                    ? $settings['oh_per_line_day'] * ($l['out'] > 0 ? $x['good_output'] / $l['out'] : 1 / $l['n']) : null;
            }
            $costs = ['direct_labour', 'ot_cost', 'allocated_oh', 'rework_cost', 'reject_cost', 'other_cost'];
            $any = collect($costs)->contains(fn ($c) => $x[$c] !== null);
            $x['total_mfg'] = $any ? collect($costs)->sum(fn ($c) => (float) $x[$c]) : null;
            $x['rate'] = $rate ?: null;
            $x['cm_earned'] = $x['cm_pc'] !== null ? $x['good_output'] * $x['cm_pc'] : null;
            $x['mfg_usd'] = $x['total_mfg'] !== null && $rate ? $x['total_mfg'] / $rate : null;
            $x['mfg_pc'] = self::div($x['mfg_usd'], $x['good_output']);
            $x['pl'] = $x['cm_earned'] !== null && $x['mfg_usd'] !== null ? $x['cm_earned'] - $x['mfg_usd'] : null;
            $x['margin'] = $x['pl'] !== null ? self::pct($x['pl'], $x['cm_earned']) : null;
            $x['target'] = $settings['target_margin'];
            $x['variance'] = $x['margin'] !== null && $x['target'] !== null ? $x['margin'] - $x['target'] : null;
            $x['status'] = match (true) {
                $x['pl'] === null => '',
                $x['pl'] < 0 => 'Loss',
                $x['margin'] !== null && $x['target'] !== null && $x['margin'] >= $x['target'] => 'On Target',
                default => 'Below Target',
            };
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);
        $cm = $c->sum('cm_earned');

        return [
            ['CM Earned (USD)', self::fmt($cm, 'money')],
            ['Mfg. Cost (USD)', self::fmt($c->sum('mfg_usd'), 'money')],
            ['Profit / Loss (USD)', self::fmt($c->sum('pl'), 'money')],
            ['Profit Margin', self::fmt(self::pct($c->sum('pl'), $cm) ?? 0, 'pct')],
            ['Good Output', self::fmt($c->sum('good_output'), 'int')],
        ];
    }
}
