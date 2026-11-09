<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\ProductionFlow;

/**
 * Daily Finishing & Packing — one row per PO for the day, the finishing → Buyer QC → packing area
 * as one flow: received into finishing, rejected anywhere in it, packed out of it.
 */
class FinishingPacking extends OpsReport
{
    private const AREA = "('finishing', 'final_qc', 'packing')";

    public function key(): string
    {
        return 'finishing-packing';
    }

    public function title(): string
    {
        return 'Daily Finishing & Packing Report';
    }

    public function department(): string
    {
        return 'Finishing / Packing';
    }

    public function guidance(): string
    {
        return 'Update each style / PO daily from finishing receipt through packing. Received (finishing input), Alteration, Rejected and Packed come from Production; Closing WIP and order balance calculate automatically (Closing WIP = Opening WIP + Received − Packed Today − Rejected). Thread Trimmed … Poly Completed can\'t pass the pieces in finishing that day (Opening WIP + Received).';
    }

    public function filters(): array
    {
        return ['buyer'];
    }

    public function columns(): array
    {
        $inArea = ['max' => fn ($row) => (int) $row['opening_wip'] + (int) $row['received'], 'max_note' => ' (pcs in finishing today)'];

        return [
            self::col('date', 'Date', 'auto', 'date'), self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'), self::col('colour', 'Colour'),
            self::col('order_qty', 'Order Qty', 'auto', 'int'),
            self::col('opening_wip', 'Opening WIP', 'auto', 'int'), self::col('received', 'Received', 'auto', 'int'),
            self::col('thread_trimmed', 'Thread Trimmed', 'input', 'int', $inArea), self::col('ironed', 'Ironed', 'input', 'int', $inArea),
            self::col('inspected', 'Inspected', 'input', 'int', $inArea),
            self::col('alteration', 'Alteration', 'auto', 'int'), self::col('rejected', 'Rejected', 'auto', 'int'),
            self::col('metal_detected', 'Metal Detected', 'input', 'int', $inArea), self::col('folded', 'Folded', 'input', 'int', $inArea),
            self::col('poly_completed', 'Poly Completed', 'input', 'int', $inArea),
            self::col('packed_today', 'Packed Today', 'auto', 'int'), self::col('cum_packed', 'Cumulative Packed', 'auto', 'int'),
            self::col('carton_qty', 'Carton Qty', 'input', 'int'),
            self::col('closing_wip', 'Closing WIP', 'formula', 'int'), self::col('process_ach', 'Process Ach. %', 'formula', 'pct'),
            self::col('order_balance', 'Order Balance', 'formula', 'int', ['min' => -1000000]),
            self::col('qa_release', 'QA Release', 'input', 'select', ['options' => ['Pending', 'Released', 'On Hold'], 'status' => true]),
            self::col('owner', 'Owner', 'input'), self::col('remarks', 'Remarks', 'input'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $d = $date->toDateString();
        $net = ProductionFlow::NET_PASS_SQL;
        $area = self::AREA;
        $sums = DB::table('msfl_prod_entries')->whereNull('deleted_at')->where('entry_date', '<=', $d)->whereRaw("stage IN {$area}")
            ->selectRaw("order_po_id,
                SUM(CASE WHEN entry_date < ? AND stage = 'finishing' AND kind = 'production' THEN input_qty ELSE 0 END) in_b,
                SUM(CASE WHEN entry_date < ? AND stage = 'packing' THEN {$net} ELSE 0 END) pk_b,
                SUM(CASE WHEN entry_date < ? THEN reject_qty ELSE 0 END) rj_b,
                SUM(CASE WHEN entry_date = ? AND stage = 'finishing' AND kind = 'production' THEN input_qty ELSE 0 END) in_d,
                SUM(CASE WHEN entry_date = ? THEN rework_qty ELSE 0 END) rw_d,
                SUM(CASE WHEN entry_date = ? THEN reject_qty ELSE 0 END) rj_d,
                SUM(CASE WHEN entry_date = ? AND stage = 'packing' THEN {$net} ELSE 0 END) pk_d,
                SUM(CASE WHEN stage = 'packing' THEN {$net} ELSE 0 END) pk_all,
                SUM(CASE WHEN entry_date = ? THEN 1 ELSE 0 END) today", array_fill(0, 8, $d))
            ->groupBy('order_po_id')->get()->keyBy('order_po_id');

        $pos = OrderPo::query()->with(['order.buyer', 'style.productType', 'color'])->whereIn('id', $sums->keys())
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed')->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id)))
            ->get();

        return $pos->map(function ($po) use ($sums, $d) {
            $s = $sums[$po->id];
            $opening = max(0, (int) $s->in_b - (int) $s->pk_b - (int) $s->rj_b);
            if (! (int) $s->today && $opening <= 0) {
                return null; // nothing in the area and nothing today
            }

            return ['_key' => 'd:' . $d . '|p:' . $po->id, '_label' => 'PO ' . $po->po_no] + $this->poCells($po) + [
                'date' => $d, 'opening_wip' => $opening, 'received' => (int) $s->in_d,
                'alteration' => (int) $s->rw_d, 'rejected' => (int) $s->rj_d,
                'packed_today' => (int) $s->pk_d, 'cum_packed' => (int) $s->pk_all,
            ];
        })->filter()->sortBy(fn ($x) => $x['buyer'] . '|' . $x['style_no'] . '|' . $x['po_no'])->values()->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['closing_wip'] = max(0, $x['opening_wip'] + $x['received'] - $x['packed_today'] - $x['rejected']);
            $x['process_ach'] = self::pct($x['packed_today'], $x['opening_wip'] + $x['received']);
            $x['order_balance'] = $x['order_qty'] - $x['cum_packed'];
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);

        return [
            ['Finishing Receipt', self::fmt($c->sum('received'), 'int')],
            ['Packed Today', self::fmt($c->sum('packed_today'), 'int')],
            ['Cum. Packed', self::fmt($c->sum('cum_packed'), 'int')],
            ['Closing WIP', self::fmt($c->sum('closing_wip'), 'int')],
            ['Order Balance', self::fmt($c->sum('order_balance'), 'int')],
        ];
    }
}
