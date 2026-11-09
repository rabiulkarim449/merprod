<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\ProductionFlow;

/** Daily Quality DHU & RFT — one row per date × line × PO × inspection point. */
class QualityDhuRft extends OpsReport
{
    public function key(): string
    {
        return 'quality-dhu-rft';
    }

    public function title(): string
    {
        return 'Daily Quality DHU & RFT Report';
    }

    public function department(): string
    {
        return 'Quality / Production';
    }

    public function guidance(): string
    {
        return 'Inspection results come from Production: Sewing end-line (hourly output), each stage\'s QC and Buyer QC. Checked = pass + alteration + reject; Defective = alteration + reject; Total Defects = the reject / rework detail rows (at least the defective pcs). DHU = Total Defects ÷ Checked; RFT = (Checked − Defective) ÷ Checked. Critical / Major / Minor come from each defect row\'s severity. Enter root cause, action, owner and due date.';
    }

    public function filters(): array
    {
        return ['buyer', 'range', 'line'];
    }

    public function columns(): array
    {
        return [
            self::col('date', 'Date', 'auto', 'date'), self::col('line', 'Line'), self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'),
            self::col('point', 'Inspection Point'),
            self::col('checked', 'Checked Pcs', 'auto', 'int'), self::col('defective', 'Defective Pcs', 'auto', 'int'), self::col('total_defects', 'Total Defects', 'auto', 'int'),
            self::col('alteration', 'Alteration Qty', 'auto', 'int'), self::col('reject', 'Reject Qty', 'auto', 'int'),
            self::col('dhu', 'DHU %', 'formula', 'pct'), self::col('rft', 'RFT %', 'formula', 'pct'), self::col('alteration_pct', 'Alteration %', 'formula', 'pct'), self::col('reject_pct', 'Reject %', 'formula', 'pct'),
            self::col('critical', 'Critical Defects', 'auto', 'int'), self::col('major', 'Major Defects', 'auto', 'int'), self::col('minor', 'Minor Defects', 'auto', 'int'),
            self::col('top1', 'Top Defect 1'), self::col('count1', 'Count 1', 'auto', 'int'), self::col('top2', 'Top Defect 2'), self::col('count2', 'Count 2', 'auto', 'int'),
            self::col('root_cause', 'Root Cause', 'input'), self::col('corrective_action', 'Corrective Action', 'input'), self::col('owner', 'Owner', 'input'),
            self::col('due_date', 'Due Date', 'input', 'date'),
            self::col('action_status', 'Action Status', 'input', 'select', ['options' => ['Open', 'In Progress', 'Closed'], 'status' => true]),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from)->toDateString() : $date->toDateString();
        $to = $r->filled('to') ? Carbon::parse($r->to)->toDateString() : $date->toDateString();
        $point = "CASE WHEN kind = 'qc' THEN CONCAT('qc:', stage) WHEN stage = 'sewing' THEN 'sewing' ELSE 'final_qc' END";

        // Inspections: sewing hourly output, Buyer QC, and every QC entry (rework repairs and pass-through stage entries aren't inspections).
        $base = DB::table('msfl_prod_entries')->whereNull('deleted_at')->whereBetween('entry_date', [$from, $to])
            ->where(fn ($q) => $q->where('kind', 'qc')->orWhere(fn ($q) => $q->where('kind', 'production')->whereIn('stage', ['sewing', 'final_qc'])))
            ->when($r->filled('line_id'), fn ($q) => $q->where('line_id', $r->line_id))
            ->when($r->filled('buyer_id'), fn ($q) => $q->whereIn('order_po_id', DB::table('msfl_order_pos as p')->join('msfl_orders as o', 'o.id', '=', 'p.order_id')->where('o.buyer_id', $r->buyer_id)->select('p.id')));

        $groups = (clone $base)->selectRaw("entry_date d, COALESCE(line_id, 0) l, order_po_id p, {$point} pt, SUM(pass_qty + rework_qty + reject_qty) checked, SUM(rework_qty + reject_qty) defective, SUM(rework_qty) alt, SUM(reject_qty) rej")
            ->groupBy('d', 'l', 'p', 'pt')->orderBy('d')->orderBy('l')->get();
        $defects = DB::table('msfl_prod_entry_defects as x')->joinSub((clone $base)->selectRaw("id, entry_date d, COALESCE(line_id, 0) l, order_po_id p, {$point} pt"), 'e', 'e.id', '=', 'x.entry_id')
            ->get(['e.d', 'e.l', 'e.p', 'e.pt', 'x.severity', 'x.defect', 'x.qty'])->groupBy(fn ($x) => $x->d . '|' . $x->l . '|' . $x->p . '|' . $x->pt);

        $pos = OrderPo::query()->with(['order.buyer', 'style.productType', 'color'])->whereIn('id', $groups->pluck('p')->unique())->get()->keyBy('id');
        $lines = Line::query()->with('floorLine')->get()->keyBy('id');

        return $groups->map(function ($g) use ($pos, $lines, $defects) {
            $po = $pos[$g->p] ?? null;
            $k = $g->d . '|' . $g->l . '|' . $g->p . '|' . $g->pt;
            $rows = $defects[$k] ?? collect();
            $top = $rows->groupBy(fn ($x) => trim((string) $x->defect) ?: 'Unspecified')->map(fn ($c) => (int) $c->sum('qty'))->sortDesc();
            $pointLabel = $g->pt === 'sewing' ? 'Sewing End-line' : ($g->pt === 'final_qc' ? 'Buyer QC (Final)' : ProductionFlow::label(substr($g->pt, 3)) . ' QC');

            return [
                '_key' => 'd:' . $g->d . '|l:' . $g->l . '|p:' . $g->p . '|i:' . $g->pt,
                '_label' => Carbon::parse($g->d)->format('d-M') . ' · ' . ($po->po_no ?? '') . ' · ' . $pointLabel,
                'date' => $g->d, 'line' => $g->l ? ($lines[$g->l]->name ?? '') : '—',
                'buyer' => $po?->order->buyer->name ?? '', 'style_no' => $po?->style->style_no ?? '', 'po_no' => $po?->po_no ?? '',
                'point' => $pointLabel,
                'checked' => (int) $g->checked, 'defective' => (int) $g->defective,
                'total_defects' => max((int) $rows->sum('qty'), (int) $g->defective),
                'alteration' => (int) $g->alt, 'reject' => (int) $g->rej,
                'critical' => (int) $rows->where('severity', 'critical')->sum('qty'),
                'major' => (int) $rows->where('severity', 'major')->sum('qty'),
                'minor' => (int) $rows->where('severity', 'minor')->sum('qty'),
                'top1' => $top->keys()->get(0), 'count1' => $top->values()->get(0),
                'top2' => $top->keys()->get(1), 'count2' => $top->values()->get(1),
            ];
        })->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['dhu'] = self::pct($x['total_defects'], $x['checked']);
            $x['rft'] = self::pct($x['checked'] - $x['defective'], $x['checked']);
            $x['alteration_pct'] = self::pct($x['alteration'], $x['checked']);
            $x['reject_pct'] = self::pct($x['reject'], $x['checked']);
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);
        $checked = $c->sum('checked');

        return [
            ['Checked Pcs', self::fmt($checked, 'int')],
            ['Total Defects', self::fmt($c->sum('total_defects'), 'int')],
            ['DHU %', self::fmt(self::pct($c->sum('total_defects'), $checked) ?? 0, 'pct')],
            ['RFT %', self::fmt(self::pct($checked - $c->sum('defective'), $checked) ?? 0, 'pct')],
            ['Reject %', self::fmt(self::pct($c->sum('reject'), $checked) ?? 0, 'pct')],
        ];
    }
}
