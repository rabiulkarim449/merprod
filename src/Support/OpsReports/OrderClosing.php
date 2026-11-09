<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Style / PO Wise Order Closing — one row per PO that went into production. */
class OrderClosing extends OpsReport
{
    public function key(): string
    {
        return 'order-closing';
    }

    public function title(): string
    {
        return 'Style / PO Wise Order Closing Report';
    }

    public function department(): string
    {
        return 'IE / Planning / Production';
    }

    public function guidance(): string
    {
        return 'Cutting and sewing dates, output, working days and the sewing plan (operators, helpers, hours, SMV, target) come from Production; shipment qty from Inventory shipments; CM per piece (sales rate) from the style / cost sheet (per dozen ÷ 12); CPM from the cost sheet, else the default CPM below. Cost / Pc considering achieved production = CPM × (operators + helpers + iron man) × used hours × 60 ÷ sewing output. Typed values override. Rows: POs with cutting, shipping in the period (default 60 days back to 30 ahead).';
    }

    public function filters(): array
    {
        return ['buyer', 'range'];
    }

    public function settingsShown(): array
    {
        return ['default_cpm'];
    }

    public function signatures(): array
    {
        return ['IE', 'Planning', 'Floor InCharge', 'QA/QC Manager', 'Production Manager', 'Managing Director'];
    }

    public function columns(): array
    {
        return [
            self::col('record_id', 'Record ID'), self::col('order_date', 'Order Receiving Date', 'auto', 'date'),
            self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'), self::col('product', 'Product'),
            self::col('order_qty', 'Order Qty', 'auto', 'int'),
            self::col('shipment_qty', 'Shipment Qty', 'auto_input', 'int'),
            self::col('cut_start', 'Cutting Start', 'auto', 'date'), self::col('cut_close', 'Cutting Closing', 'auto', 'date'),
            self::col('cut_days', 'Total days Consumed in Cutting', 'formula', 'int'),
            self::col('sew_start', 'Sewing Start', 'auto', 'date'), self::col('sew_end', 'Sewing Completion', 'auto', 'date'),
            self::col('machines', 'Machines Used', 'auto_input', 'int'),
            self::col('operators', 'Operators Used', 'auto_input', 'int'),
            self::col('ironman', 'Iron Man Used', 'input', 'int'),
            self::col('helpers', 'Helpers Used', 'auto_input', 'int'),
            self::col('working_days', 'Working Days', 'auto_input', 'int'),
            self::col('hours_day', 'Hours / Day', 'auto_input', 'num', ['max' => 24]),
            self::col('total_hours', 'Total Used Hours', 'formula', 'num'),
            self::col('smv', 'SMV', 'auto_input', 'num'),
            self::col('hourly_target', 'Hourly Target', 'formula', 'int'),
            self::col('hourly_achieve', 'Hourly Achievement', 'formula', 'int'),
            self::col('target_day', 'Target Production/day', 'auto_input', 'int'),
            self::col('achieved_day', 'Achieved Production of the Day', 'formula', 'int'),
            self::col('achievement', 'Achievement %', 'formula', 'pct'),
            self::col('avg_hourly', 'Avg. Hourly Output', 'formula', 'num'),
            self::col('cpm', 'CPM', 'auto_input', 'money'),
            self::col('cost_sales', 'Total Cost/ Pc- Considering Sales Rate', 'auto_input', 'money'),
            self::col('cost_achieved', 'Total Cost/ Pc- Considering Achived Production', 'formula', 'money'),
            self::col('pnl_pc', 'PnL / Pcs', 'formula', 'money'),
            self::col('pnl_order', 'PnL of whole Order', 'formula', 'money'),
            self::col('cm_margin', 'CM Margin %', 'formula', 'pct'),
            self::col('closure', 'Closure Status', 'auto_input', 'select', ['options' => ['Open', 'Closed', 'Short Shipped', 'Excess Shipped'], 'status' => true]),
            self::col('remarks', 'Remarks', 'input'),
            self::col('mgmt_action', 'Management Action', 'input'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from) : $date->copy()->subDays(60);
        $to = $r->filled('to') ? Carbon::parse($r->to) : $date->copy()->addDays(30);

        $cut = DB::table('msfl_prod_cuttings')->whereNull('deleted_at')->groupBy('order_po_id')
            ->selectRaw('order_po_id, MIN(cutting_date) s, MAX(cutting_date) e')->get()->keyBy('order_po_id');
        $pos = $this->pos($r)->whereIn('id', $cut->keys())->whereBetween('shipment_date', [$from->toDateString(), $to->toDateString()])->orderBy('shipment_date')->get();
        $ids = $pos->pluck('id');

        $sew = DB::table('msfl_prod_entries')->whereNull('deleted_at')->where('stage', 'sewing')->whereIn('order_po_id', $ids)
            ->selectRaw('order_po_id, MIN(CASE WHEN input_qty > 0 OR pass_qty > 0 THEN entry_date END) s, MAX(CASE WHEN pass_qty > 0 THEN entry_date END) e,
                COUNT(DISTINCT CASE WHEN pass_qty > 0 THEN entry_date END) days, SUM(' . \ME\MerchandisingSfl\Services\ProductionFlow::NET_PASS_SQL . ') out_')
            ->groupBy('order_po_id')->get()->keyBy('order_po_id');
        $plans = DB::table('msfl_prod_sewing_plans')->whereIn('order_po_id', $ids)->groupBy('order_po_id')
            ->selectRaw('order_po_id, AVG(operators) op, AVG(helpers) hp, AVG(working_hours) wh, AVG(NULLIF(smv, 0)) smv, AVG(target) tg')->get()->keyBy('order_po_id');
        $lines = DB::table('msfl_prod_entries')->whereNull('deleted_at')->where('stage', 'sewing')->whereIn('order_po_id', $ids)->whereNotNull('line_id')
            ->distinct()->get(['order_po_id', 'line_id'])->groupBy('order_po_id');
        $machinesByLine = DB::table('msfl_line_machines')->groupBy('line_id')->selectRaw('line_id, SUM(qty) q')->pluck('q', 'line_id');
        $shipped = DB::table('inv_shipment_items as si')->join('inv_shipments as s', 's.id', '=', 'si.shipment_id')->whereNull('s.deleted_at')
            ->whereIn('si.msfl_order_po_id', $ids)->groupBy('si.msfl_order_po_id')->selectRaw('si.msfl_order_po_id po, SUM(si.quantity) q')->pluck('q', 'po');
        $cm = $this->cmPerPiece($pos->pluck('style_id')->unique()->all());

        return $pos->map(function ($po) use ($cut, $sew, $plans, $lines, $machinesByLine, $shipped, $cm, $settings) {
            $s = $sew[$po->id] ?? null;
            $p = $plans[$po->id] ?? null;
            $machines = collect($lines[$po->id] ?? [])->sum(fn ($l) => (int) ($machinesByLine[$l->line_id] ?? 0));

            return ['_key' => 'po:' . $po->id, '_label' => 'PO ' . $po->po_no] + $this->poCells($po) + [
                'record_id' => $po->order->order_no ?? '',
                'order_date' => $po->order->order_date?->toDateString(),
                'shipment_qty' => (int) round((float) ($shipped[$po->id] ?? 0)),
                'cut_start' => $cut[$po->id]->s ?? null, 'cut_close' => $cut[$po->id]->e ?? null,
                'sew_start' => $s->s ?? null, 'sew_end' => $s->e ?? null,
                'machines' => $machines ?: null,
                'operators' => $p ? (int) round($p->op) : null,
                'helpers' => $p ? (int) round($p->hp) : null,
                'working_days' => (int) ($s->days ?? 0),
                'hours_day' => $p && $p->wh ? round((float) $p->wh, 2) : null,
                'smv' => $p && $p->smv ? round((float) $p->smv, 2) : ((float) $po->style->smv ?: null),
                'target_day' => $p && $p->tg ? (int) round($p->tg) : null,
                'cpm' => $cm[$po->style_id]['cpm'] ?? $settings['default_cpm'],
                'cost_sales' => $cm[$po->style_id]['cm'] ?? null,
                '_output' => (int) ($s->out_ ?? 0),
            ];
        })->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['cut_days'] = $x['cut_start'] && $x['cut_close'] ? Carbon::parse($x['cut_start'])->diffInDays(Carbon::parse($x['cut_close'])) + 1 : null;
            $x['total_hours'] = $x['working_days'] && $x['hours_day'] ? round($x['working_days'] * $x['hours_day'], 2) : null;
            $x['achieved_day'] = $x['working_days'] ? (int) round($x['_output'] / $x['working_days']) : null;
            $x['hourly_target'] = $x['target_day'] && $x['hours_day'] ? (int) round($x['target_day'] / $x['hours_day']) : null;
            $x['hourly_achieve'] = $x['achieved_day'] !== null && $x['hours_day'] ? (int) round($x['achieved_day'] / $x['hours_day']) : null;
            $x['achievement'] = self::pct($x['achieved_day'], $x['target_day']);
            $x['avg_hourly'] = $x['total_hours'] ? round($x['_output'] / $x['total_hours'], 2) : null;
            $people = (int) $x['operators'] + (int) $x['helpers'] + (int) $x['ironman'];
            $x['cost_achieved'] = $x['cpm'] && $people && $x['total_hours'] && $x['_output'] ? $x['cpm'] * $people * $x['total_hours'] * 60 / $x['_output'] : null;
            $x['pnl_pc'] = $x['cost_sales'] !== null && $x['cost_achieved'] !== null ? $x['cost_sales'] - $x['cost_achieved'] : null;
            $x['pnl_order'] = $x['pnl_pc'] !== null ? $x['pnl_pc'] * ($x['shipment_qty'] ?: $x['_output']) : null;
            $x['cm_margin'] = $x['pnl_pc'] !== null ? self::pct($x['pnl_pc'], $x['cost_sales']) : null;
            if (($x['_manual']['closure'] ?? null) === null) {
                $x['closure'] = $x['shipment_qty'] >= $x['order_qty'] && $x['order_qty'] > 0 ? ($x['shipment_qty'] > $x['order_qty'] ? 'Excess Shipped' : 'Closed') : 'Open';
            }
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        return [];
    }
}
