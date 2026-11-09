<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\ReportInput;

/** Daily Cutting Production — one row per PO in cutting on the day. */
class CuttingDaily extends OpsReport
{
    public function key(): string
    {
        return 'cutting-daily';
    }

    public function title(): string
    {
        return 'Daily Cutting Production Report';
    }

    public function department(): string
    {
        return 'Cutting / CAD / IE';
    }

    public function guidance(): string
    {
        return 'Enter daily planned cutting, recut and marker efficiency. Actual Cut and Fabric Issued come from Production → Cutting, Cut Reject from Production → QC (cutting), Standard Cons./Pc from the BOM fabric (with wastage). Net cut, balance and consumption variance are automatic. Recut can\'t pass the PO\'s cutting rejects not yet recut. Rows: POs cut that day, still being cut, or with PCD within a week.';
    }

    public function filters(): array
    {
        return ['buyer'];
    }

    public function columns(): array
    {
        return [
            self::col('date', 'Date', 'auto', 'date'), self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'), self::col('colour', 'Colour'),
            self::col('planned_cut', 'Planned Cut', 'input', 'int'),
            self::col('actual_cut', 'Actual Cut', 'auto', 'int'), self::col('cut_reject', 'Cut Reject', 'auto', 'int'),
            self::col('recut', 'Recut Qty', 'input', 'int', ['max' => fn ($row) => max(0, $row['_cum_reject'] - $row['_prev_recut']), 'max_note' => ' (cut rejects not yet recut)']),
            self::col('net_good', 'Net Good Cut', 'formula', 'int', ['min' => -1000000]),
            self::col('cut_balance', 'Cut Balance', 'formula', 'int', ['min' => -1000000]),
            self::col('achievement', 'Achievement %', 'formula', 'pct'),
            self::col('fabric_issued', 'Fabric Issued (m)', 'auto_input', 'num'),
            self::col('marker_eff', 'Marker Efficiency %', 'input', 'pct'),
            self::col('standard_cons', 'Standard Cons./Pc', 'auto_input', 'num'),
            self::col('actual_cons', 'Actual Cons./Pc', 'formula', 'num'),
            self::col('cons_var', 'Consumption Var. %', 'formula', 'pct'),
            self::col('remarks', 'Remarks', 'input'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $d = $date->toDateString();
        $cut = DB::table('msfl_prod_cuttings')->whereNull('deleted_at')->where('cutting_date', '<=', $d)->groupBy('order_po_id')
            ->selectRaw('order_po_id, SUM(total_qty) cum, SUM(CASE WHEN cutting_date = ? THEN total_qty ELSE 0 END) today,
                SUM(CASE WHEN cutting_date = ? THEN COALESCE(fabric_used, 0) ELSE 0 END) fabric', [$d, $d])->get()->keyBy('order_po_id');
        $rej = DB::table('msfl_prod_entries')->whereNull('deleted_at')->where('stage', 'cutting')->where('entry_date', '<=', $d)->groupBy('order_po_id')
            ->selectRaw('order_po_id, SUM(reject_qty) cum, SUM(CASE WHEN entry_date = ? THEN reject_qty ELSE 0 END) today', [$d])->get()->keyBy('order_po_id');
        $typed = ReportInput::query()->where('report', $this->key())->where('row_key', 'like', 'd:' . $d . '|p:%')->pluck('row_key')
            ->map(fn ($k) => (int) substr($k, strrpos($k, ':') + 1));

        $pos = $this->pos($r)->get()->filter(function ($po) use ($cut, $d, $typed) {
            $c = $cut[$po->id] ?? null;
            $soon = $po->pcd_date && $po->pcd_date->between(Carbon::parse($d)->subDays(3), Carbon::parse($d)->addDays(7));

            return ($c && ((int) $c->today > 0 || (int) $c->cum < $po->po_qty)) || (! $c && $soon) || $typed->contains($po->id);
        })->values();

        // Recut typed on earlier days counts toward the balance (and limits today's recut).
        $prevRecut = ReportInput::query()->where('report', $this->key())->where('field', 'recut')->get(['row_key', 'value'])
            ->filter(fn ($i) => preg_match('/^d:([\d-]+)\|p:(\d+)$/', $i->row_key, $m) && $m[1] < $d)
            ->groupBy(fn ($i) => (int) substr($i->row_key, strrpos($i->row_key, ':') + 1))->map(fn ($g) => (int) $g->sum('value'));
        $std = $this->standardCons($pos);

        return $pos->map(fn ($po) => ['_key' => 'd:' . $d . '|p:' . $po->id, '_label' => 'PO ' . $po->po_no] + $this->poCells($po) + [
            'date' => $d,
            'actual_cut' => (int) ($cut[$po->id]->today ?? 0), 'cut_reject' => (int) ($rej[$po->id]->today ?? 0),
            'fabric_issued' => ($f = (float) ($cut[$po->id]->fabric ?? 0)) > 0 ? round($f, 2) : null,
            'standard_cons' => $std[$po->id] ?? null,
            '_cum_cut' => (int) ($cut[$po->id]->cum ?? 0), '_cum_reject' => (int) ($rej[$po->id]->cum ?? 0), '_prev_recut' => (int) ($prevRecut[$po->id] ?? 0),
        ])->sortBy(fn ($x) => $x['buyer'] . '|' . $x['style_no'] . '|' . $x['po_no'])->values()->all();
    }

    /** po_id => fabric consumption per piece with wastage, from the order's BOM (else the style's). */
    private function standardCons($pos): array
    {
        $boms = DB::table('msfl_bom_items as bi')->join('msfl_boms as b', 'b.id', '=', 'bi.bom_id')->leftJoin('msfl_items as i', 'i.id', '=', 'bi.item_id')
            ->whereNull('b.deleted_at')->whereIn('b.style_id', $pos->pluck('style_id')->unique())
            ->where(fn ($q) => $q->where('bi.item_type', 'fabric')->orWhere('i.type', 'fabric'))
            ->orderBy('b.id')->orderBy('bi.id')->get(['b.style_id', 'b.order_id', 'bi.consumption', 'bi.wastage_percent']);
        $out = [];
        foreach ($pos as $po) {
            $f = $boms->first(fn ($x) => $x->style_id == $po->style_id && $x->order_id == $po->order_id)
                ?? $boms->first(fn ($x) => $x->style_id == $po->style_id && ! $x->order_id);
            if ($f) {
                $out[$po->id] = round((float) $f->consumption * (1 + (float) $f->wastage_percent / 100), 4);
            }
        }

        return $out;
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $recut = (int) $x['recut'];
            $x['net_good'] = $x['actual_cut'] - $x['cut_reject'] + $recut;
            $x['cut_balance'] = $x['order_qty'] - ($x['_cum_cut'] - $x['_cum_reject'] + $x['_prev_recut'] + $recut);
            $x['achievement'] = self::pct($x['actual_cut'], $x['planned_cut']);
            $x['actual_cons'] = $x['fabric_issued'] !== null && $x['actual_cut'] ? round($x['fabric_issued'] / $x['actual_cut'], 4) : null;
            $x['cons_var'] = $x['actual_cons'] !== null && $x['standard_cons'] ? ($x['actual_cons'] - $x['standard_cons']) / $x['standard_cons'] * 100 : null;
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);
        $withStd = $c->filter(fn ($x) => $x['actual_cons'] !== null && $x['standard_cons']);
        $stdFabric = $withStd->sum(fn ($x) => $x['standard_cons'] * $x['actual_cut']);

        return [
            ['Planned Cut', self::fmt($c->sum('planned_cut'), 'int')],
            ['Net Good Cut', self::fmt($c->sum('net_good'), 'int')],
            ['Cut Balance', self::fmt($c->sum('cut_balance'), 'int')],
            ['Cut Reject %', self::fmt(self::pct($c->sum('cut_reject'), $c->sum('actual_cut')) ?? 0, 'pct')],
            ['Consumption Var. %', self::fmt($stdFabric ? ($withStd->sum('fabric_issued') - $stdFabric) / $stdFabric * 100 : 0, 'pct')],
        ];
    }
}
