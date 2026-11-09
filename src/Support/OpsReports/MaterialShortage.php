<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily Material Shortage — one row per style × material item. Inventory receives (GRN) and
 * requisitions are kept against the style (not a PO), matched the way the Inventory stock
 * screens match them: the Merchandising style, or the same buyer with the same style text.
 */
class MaterialShortage extends OpsReport
{
    public function key(): string
    {
        return 'material-shortage';
    }

    public function title(): string
    {
        return 'Daily Material Shortage Report';
    }

    public function department(): string
    {
        return 'Store / Merchandising';
    }

    public function guidance(): string
    {
        return 'Required = requested on the style\'s Inventory requisitions (type a figure to override, e.g. from the BOM); Received = posted GRNs for the style; Approved / Issued = the requisitions. Shortage = Required − Received. Required By = the earliest PCD of the style\'s open POs. Risk: Critical ≤ 3 days to need, High ≤ 7, Medium otherwise, OK when nothing is short. Rows: styles with a PO not fully packed.';
    }

    public function filters(): array
    {
        return ['buyer', 'style'];
    }

    public function columns(): array
    {
        return [
            self::col('date', 'Date', 'auto', 'date'), self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'),
            self::col('material_type', 'Material Type'), self::col('material', 'Material Description'), self::col('uom', 'UOM'),
            self::col('required', 'Required Qty', 'auto_input', 'num'),
            self::col('received', 'Received Qty', 'auto', 'num'), self::col('approved', 'Approved Qty', 'auto', 'num'), self::col('issued', 'Issued Qty', 'auto', 'num'),
            self::col('shortage', 'Shortage Qty', 'formula', 'num'), self::col('shortage_pct', 'Shortage %', 'formula', 'pct'),
            self::col('required_by', 'Required By Date', 'auto_input', 'date'),
            self::col('days_to_need', 'Days to Need', 'formula', 'int', ['min' => -100000]),
            self::col('risk', 'Risk Status', 'formula', 'text', ['status' => true]),
            self::col('supplier', 'Supplier', 'auto_input'), self::col('owner', 'Owner', 'input'),
            self::col('expected_arrival', 'Expected Arrival', 'input', 'date'), self::col('remarks', 'Remarks', 'input'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $pos = $this->pos($r)->when($r->filled('style_id'), fn ($q) => $q->where('style_id', $r->style_id))->get();
        $packed = $this->netPass($pos->pluck('id'));
        $open = $pos->filter(fn ($po) => ($packed[$po->id]['packing'] ?? 0) < $po->po_qty)->groupBy('style_id');
        if ($open->isEmpty()) {
            return [];
        }
        $styles = $open->map(fn ($g) => $g->first()->style);
        $invBuyer = DB::table('inv_buyers')->whereNull('deleted_at')->get(['id', 'name'])
            ->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->name)) => $b->id]);

        // style_id the inventory row belongs to: its Merchandising style, else buyer + style text.
        $byText = [];
        foreach ($open as $styleId => $g) {
            $buyer = $invBuyer[mb_strtolower(trim($g->first()->order->buyer->name ?? ''))] ?? null;
            $byText[($buyer ?? 'x') . '|' . trim($styles[$styleId]->style_no)] = $styleId;
        }
        $owner = fn ($x) => $x->msfl_style_id ? ($open->has($x->msfl_style_id) ? $x->msfl_style_id : null) : ($byText[$x->buyer_id . '|' . trim((string) $x->style)] ?? null);
        $match = fn ($q, $t) => $q->where(fn ($w) => $w->whereIn("{$t}.msfl_style_id", $open->keys())
            ->orWhere(fn ($o) => $o->whereNull("{$t}.msfl_style_id")->whereIn(DB::raw("TRIM({$t}.style)"), $styles->pluck('style_no')->map(fn ($s) => trim($s)))));

        $reqs = DB::table('inv_requisition_items as ri')->join('inv_requisitions as rq', 'rq.id', '=', 'ri.requisition_id')
            ->whereNull('rq.deleted_at')->whereNotIn('rq.status', ['rejected', 'cancelled'])->tap(fn ($q) => $match($q, 'rq'))
            ->get(['rq.msfl_style_id', 'rq.buyer_id', 'rq.style', 'rq.requisition_date', 'ri.item_id', 'ri.requested_qty', 'ri.approved_qty', 'ri.issued_qty']);
        $grns = DB::table('inv_grn_items as gi')->join('inv_grns as g', 'g.id', '=', 'gi.grn_id')->leftJoin('inv_suppliers as su', 'su.id', '=', 'g.supplier_id')
            ->whereNull('g.deleted_at')->where('g.status', 'posted')->tap(fn ($q) => $match($q, 'g'))
            ->get(['g.msfl_style_id', 'g.buyer_id', 'g.style', 'g.receive_date', 'g.source_type', 'su.name as supplier', 'gi.item_id', 'gi.received_qty']);

        $cells = [];
        foreach ($reqs as $x) {
            if (! ($sid = $owner($x))) {
                continue;
            }
            $c = &$cells[$sid . '|' . $x->item_id];
            $c['required'] = ($c['required'] ?? 0) + (float) $x->requested_qty;
            $c['approved'] = ($c['approved'] ?? 0) + (float) $x->approved_qty;
            $c['issued'] = ($c['issued'] ?? 0) + (float) $x->issued_qty;
            $c['date'] = min($c['date'] ?? '9999', $x->requisition_date);
            unset($c);
        }
        foreach ($grns as $x) {
            if (! ($sid = $owner($x))) {
                continue;
            }
            $c = &$cells[$sid . '|' . $x->item_id];
            $c['received'] = ($c['received'] ?? 0) + (float) $x->received_qty;
            $c['date'] = min($c['date'] ?? '9999', $x->receive_date);
            $c['suppliers'][] = $x->source_type === 'buyer_supplied' ? 'Buyer supplied' : ($x->supplier ?: 'Supplier');
            unset($c);
        }

        $items = DB::table('inv_items as it')->leftJoin('inv_item_categories as c', 'c.id', '=', 'it.category_id')->leftJoin('inv_units as u', 'u.id', '=', 'it.unit_id')
            ->whereIn('it.id', collect(array_keys($cells))->map(fn ($k) => (int) explode('|', $k)[1])->unique())
            ->get(['it.id', 'it.item_code', 'it.item_name', 'c.name as category', 'u.short_name as unit'])->keyBy('id');

        $rows = [];
        foreach ($cells as $k => $c) {
            [$sid, $itemId] = array_map('intval', explode('|', $k));
            $g = $open[$sid];
            $it = $items[$itemId] ?? null;
            $rows[] = [
                '_key' => 's:' . $sid . '|i:' . $itemId, '_label' => ($styles[$sid]->style_no ?? '') . ' · ' . ($it->item_name ?? $itemId),
                'date' => ($c['date'] ?? '9999') === '9999' ? null : Carbon::parse($c['date'])->toDateString(),
                'buyer' => $g->first()->order->buyer->name ?? '', 'style_no' => $styles[$sid]->style_no ?? '',
                'po_no' => $g->pluck('po_no')->unique()->implode(', '),
                'material_type' => $it->category ?? '', 'material' => trim(($it->item_code ?? '') . ' ' . ($it->item_name ?? '')), 'uom' => $it->unit ?? '',
                'required' => round($c['required'] ?? 0, 2) ?: null, 'received' => round($c['received'] ?? 0, 2),
                'approved' => round($c['approved'] ?? 0, 2), 'issued' => round($c['issued'] ?? 0, 2),
                'required_by' => $g->min(fn ($po) => $po->pcd_date?->toDateString()),
                'supplier' => collect($c['suppliers'] ?? [])->unique()->implode(', ') ?: null,
            ];
        }

        return collect($rows)->sortBy(fn ($x) => $x['buyer'] . '|' . $x['style_no'] . '|' . $x['material'])->values()->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['shortage'] = $x['required'] !== null ? max(0, round($x['required'] - $x['received'], 2)) : null;
            $x['shortage_pct'] = $x['shortage'] !== null ? self::pct($x['shortage'], $x['required']) : null;
            $x['days_to_need'] = $x['required_by'] ? (int) $date->diffInDays(Carbon::parse($x['required_by']), false) : null;
            $x['risk'] = match (true) {
                ! $x['shortage'] => 'OK',
                $x['days_to_need'] !== null && $x['days_to_need'] <= 3 => 'Critical',
                $x['days_to_need'] !== null && $x['days_to_need'] <= 7 => 'High',
                default => 'Medium',
            };
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);
        $withReq = $c->filter(fn ($x) => $x['required'] > 0);

        return [
            ['Material Items', self::fmt($c->count(), 'int')],
            ['Required Qty', self::fmt($c->sum('required'), 'num')],
            ['Shortage Qty', self::fmt($c->sum('shortage'), 'num')],
            ['Critical Items', self::fmt($c->where('risk', 'Critical')->count(), 'int')],
            ['Avg. Shortage %', self::fmt($withReq->avg('shortage_pct') ?? 0, 'pct')],
        ];
    }
}
