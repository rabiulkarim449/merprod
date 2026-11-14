<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ME\MerchandisingSfl\Models\Bom;
use ME\MerchandisingSfl\Models\BomItem;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * Material Requirement Plan (client's MRP sheet) — one row per material of an order's style, from the
 * order's latest BOM. View / print only: every cell comes from an entry somewhere.
 *
 *   Merchandising   order qty (narrowed by the BOM line's color / size), consumption, wastage, supplier,
 *                   required date (earliest PCD of the style's POs)
 *   Inventory       only when installed and the BOM item is linked to a store item (Items → Inventory Item):
 *                   On Order / Booking Ref / Expected In-House — open Store Orders made from a purchase
 *                   requisition of this style; Received / Inspection / Approved — posted GRNs of this style
 *                   (the GRN's own style, else its Store Order's requisition), PO-tagged ones only for this order.
 *
 * Gross = order qty × consumption; Net = gross × (1 + wastage %); Shortage = Net − (Received + On Order).
 * Read straight from Inventory's tables (no Inventory classes) so the packages stay independent.
 */
class MaterialRequirementPlan extends OpsReport
{
    public function key(): string
    {
        return 'mrp';
    }

    public function title(): string
    {
        return 'Material Requirement Plan (MRP)';
    }

    public function department(): string
    {
        return 'Merchandising / Store';
    }

    public function guidance(): string
    {
        return 'Fabrics, trims & packing per order style from its latest BOM. Gross = Order Qty × Consumption; Net = Gross × (1 + Wastage %). '
            . 'Store figures need the BOM item linked to a store item (Master Data → Items → Inventory Item, or Sync from Inventory): '
            . 'On Order / Booking Ref / Expected = open Store Orders from purchase requisitions of the style; Received / Inspection / Approved = posted GRNs of the style. '
            . 'Stock + On Order = Received + On Order; Shortage = Net − that. Status: In-house, On Order, Short, Critical (short and needed within 3 days or overdue), No Store Link.';
    }

    public function filters(): array
    {
        return ['buyer', 'style'];
    }

    public function landscape(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            self::col('buyer', 'Buyer'), self::col('style_no', 'Style'), self::col('po_no', 'PO'),
            self::col('category', 'Item Category'), self::col('material', 'Material Description'), self::col('item_code', 'Item Code'),
            self::col('color', 'Color'), self::col('size', 'Size / Width'), self::col('uom', 'UOM'),
            self::col('order_qty', 'Order Qty', 'auto', 'int'), self::col('consumption', 'Consumption / Pc', 'auto', 'num'),
            self::col('gross', 'Gross Requirement', 'formula', 'num'), self::col('wastage', 'Wastage %', 'auto', 'pct'),
            self::col('net', 'Net Requirement', 'formula', 'num'), self::col('covered', 'Stock + On Order', 'formula', 'num'),
            self::col('shortage', 'Shortage Qty', 'formula', 'num'), self::col('supplier', 'Supplier'),
            self::col('booking_ref', 'PO / Booking Ref'), self::col('required_date', 'Required Date', 'auto', 'date'),
            self::col('expected', 'Expected In-House', 'auto', 'date'), self::col('status', 'Status', 'formula', 'text', ['status' => true]),
            self::col('received', 'Received Qty', 'auto', 'num'), self::col('inspection', 'Inspection Status', 'auto', 'text', ['status' => true]),
            self::col('approved', 'Approved Qty', 'auto', 'num'), self::col('remarks', 'Remarks'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $boms = $this->latestOrderBoms($r);
        if ($boms->isEmpty()) {
            return [];
        }

        $pos = OrderPo::query()->with('sizes')->whereIn('order_id', $boms->pluck('order_id'))->get()
            ->groupBy(fn ($p) => $p->order_id . '|' . $p->style_id);
        $store = $this->storeFigures($boms, $pos);

        $rows = [];
        foreach ($boms as $bom) {
            $stylePos = $pos[$bom->order_id . '|' . $bom->style_id] ?? collect();
            foreach ($bom->items as $line) {
                $qty = $this->lineQty($line, $stylePos);
                if ($qty <= 0) {
                    continue; // a line for a color / size this order doesn't have
                }
                $item = $line->item;
                $invId = $item?->inv_item_id;
                $s = $invId ? ($store[$bom->style_id . '|' . $bom->order_id . '|' . $invId] ?? []) : null;

                $rows[] = [
                    '_key' => 'b:' . $bom->id . '|l:' . $line->id,
                    '_label' => ($bom->style->style_no ?? '') . ' · ' . ($item->name ?? $line->item_id),
                    '_linked' => $invId !== null && $store !== null,
                    'buyer' => $bom->order->buyer->name ?? '',
                    'style_no' => $bom->style->style_no ?? '',
                    'po_no' => $stylePos->pluck('po_no')->unique()->implode(', '),
                    'category' => $item->category->name ?? '',
                    'material' => trim(($item->name ?? '') . ($item?->composition ? ', ' . $item->composition : '') . ($item?->gsm ? ', ' . rtrim(rtrim((string) $item->gsm, '0'), '.') . ' GSM' : '')),
                    'item_code' => $item->code ?? '',
                    'color' => $line->color->name ?? 'All',
                    'size' => $line->size->name ?? 'All',
                    'uom' => $line->uom->code ?? ($item->uom->code ?? ''),
                    'order_qty' => $qty,
                    'consumption' => (float) $line->consumption,
                    'wastage' => (float) $line->wastage_percent,
                    'supplier' => $line->supplier->name ?? ($item->defaultSupplier->name ?? ''),
                    'booking_ref' => $s ? (implode(', ', array_unique($s['refs'] ?? [])) ?: null) : null,
                    'required_date' => $stylePos->min(fn ($p) => $p->pcd_date?->toDateString()),
                    'expected' => $s['expected'] ?? null,
                    'on_order' => $s === null ? null : round($s['on_order'] ?? 0, 2),
                    'received' => $s === null ? null : round($s['received'] ?? 0, 2),
                    'inspection' => $s === null ? null : $this->inspectionText($s['inspection'] ?? []),
                    'approved' => $s === null ? null : round($s['approved'] ?? 0, 2),
                    'remarks' => trim(collect([$line->placement, $line->remarks])->filter()->implode(' · ')) ?: null,
                ];
            }
        }

        return collect($rows)->sortBy(fn ($x) => $x['buyer'] . '|' . $x['style_no'] . '|' . $x['category'] . '|' . $x['material'])->values()->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['gross'] = round($x['order_qty'] * $x['consumption'], 2);
            $x['net'] = round($x['gross'] * (1 + $x['wastage'] / 100), 2);
            $linked = $x['_linked'];
            $x['covered'] = $linked ? round($x['received'] + $x['on_order'], 2) : null;
            $x['shortage'] = $linked ? max(0, round($x['net'] - $x['covered'], 2)) : null;
            $days = $x['required_date'] ? (int) $date->diffInDays(Carbon::parse($x['required_date']), false) : null;
            $x['status'] = match (true) {
                ! $linked => 'No Store Link',
                $x['net'] <= 0 => 'OK',
                $x['received'] >= $x['net'] => 'In-house',
                $x['shortage'] <= 0 => 'On Order',
                $days !== null && $days <= 3 => 'Critical',
                default => 'Short',
            };
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);

        return [
            ['Material Lines', self::fmt($c->count(), 'int')],
            ['Total Required', self::fmt($c->sum('net'), 'num')],
            ['Total Shortage', self::fmt($c->sum('shortage'), 'num')],
            ['Critical Items', self::fmt($c->where('status', 'Critical')->count(), 'int')],
        ];
    }

    /** The latest BOM version of each confirmed order's style (order BOMs only — a style BOM has no qty). */
    private function latestOrderBoms(Request $r): Collection
    {
        return Bom::query()->with(['order.buyer', 'style', 'items.item.category', 'items.item.uom', 'items.item.defaultSupplier', 'items.color', 'items.size', 'items.uom', 'items.supplier'])
            ->whereNotNull('order_id')
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed')->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id)))
            ->when($r->filled('style_id'), fn ($q) => $q->where('style_id', $r->style_id))
            ->orderByDesc('version')->get()
            ->unique(fn ($b) => $b->order_id . '|' . $b->style_id)->values();
    }

    /** Pieces of the style's POs the line is for: all, or only its color, and only its size. */
    private function lineQty(BomItem $line, Collection $pos): int
    {
        return (int) $pos->filter(fn ($p) => ! $line->color_id || (int) $p->color_id === (int) $line->color_id)
            ->sum(fn ($p) => $line->size_id ? (int) $p->sizes->where('size_id', $line->size_id)->sum('qty') : (int) $p->po_qty);
    }

    /**
     * Store figures per "style|order|inv item": on_order, refs, expected, received, approved, inspection.
     * Null when Inventory is not installed (store columns then stay empty).
     */
    private function storeFigures(Collection $boms, Collection $pos): ?array
    {
        if (! Schema::hasTable('inv_grn_items') || ! Schema::hasColumn('msfl_items', 'inv_item_id')) {
            return null;
        }
        $itemIds = $boms->flatMap(fn ($b) => $b->items->pluck('item.inv_item_id'))->filter()->unique()->values();
        if ($itemIds->isEmpty()) {
            return [];
        }
        $styleIds = $boms->pluck('style_id')->unique()->values();
        // PO id → order id, to keep a PO-tagged record on its own order only.
        $poOrder = $pos->collapse()->pluck('order_id', 'id');
        $orders = $boms->groupBy('style_id')->map(fn ($g) => $g->pluck('order_id')->unique()->all());
        $out = [];
        $add = function ($styleId, $poId, $itemId, callable $fn) use (&$out, $orders, $poOrder) {
            $targets = $poId && isset($poOrder[$poId]) ? [$poOrder[$poId]] : ($orders[$styleId] ?? []);
            foreach ($targets as $orderId) {
                $fn($out[$styleId . '|' . $orderId . '|' . $itemId]);
            }
        };

        $prLinked = Schema::hasColumn('inv_purchase_requisitions', 'msfl_style_id');
        if ($prLinked) {
            DB::table('inv_purchase_order_items as poi')
                ->join('inv_purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->join('inv_purchase_requisitions as pr', 'pr.id', '=', 'po.purchase_requisition_id')
                ->whereNull('po.deleted_at')->whereNull('pr.deleted_at')
                ->whereIn('pr.msfl_style_id', $styleIds)->whereIn('poi.item_id', $itemIds)
                ->get(['pr.msfl_style_id', 'pr.msfl_order_po_id', 'poi.item_id', 'poi.quantity', 'poi.received_qty', 'po.po_number', 'po.status', 'po.expected_date'])
                ->each(fn ($x) => $add($x->msfl_style_id, $x->msfl_order_po_id, $x->item_id, function (&$c) use ($x) {
                    $c['refs'][] = $x->po_number;
                    if ($x->status !== 'closed') {
                        $c['on_order'] = ($c['on_order'] ?? 0) + max(0, (float) $x->quantity - (float) $x->received_qty);
                        if ($x->expected_date) {
                            $c['expected'] = min($c['expected'] ?? '9999-12-31', $x->expected_date);
                        }
                    }
                }));
        }

        $grns = DB::table('inv_grn_items as gi')->join('inv_grns as g', 'g.id', '=', 'gi.grn_id')
            ->where('g.status', 'posted')->whereNull('g.deleted_at')->whereIn('gi.item_id', $itemIds);
        if ($prLinked) {
            $grns->leftJoin('inv_purchase_orders as po', 'po.id', '=', 'g.purchase_order_id')
                ->leftJoin('inv_purchase_requisitions as pr', 'pr.id', '=', 'po.purchase_requisition_id')
                ->where(fn ($q) => $q->whereIn('g.msfl_style_id', $styleIds)->orWhere(fn ($q) => $q->whereNull('g.msfl_style_id')->whereIn('pr.msfl_style_id', $styleIds)))
                ->select(DB::raw('COALESCE(g.msfl_style_id, pr.msfl_style_id) as style_id'), DB::raw('COALESCE(g.msfl_order_po_id, pr.msfl_order_po_id) as po_id'));
        } else {
            $grns->whereIn('g.msfl_style_id', $styleIds)->select('g.msfl_style_id as style_id', 'g.msfl_order_po_id as po_id');
        }
        $hasInspection = Schema::hasColumn('inv_grn_items', 'inspection_status');
        $grns->addSelect('gi.item_id', 'gi.received_qty', 'gi.rejected_qty', $hasInspection ? 'gi.inspection_status' : DB::raw('NULL as inspection_status'))
            ->get()
            ->each(fn ($x) => $add($x->style_id, $x->po_id, $x->item_id, function (&$c) use ($x) {
                $received = (float) $x->received_qty;
                $c['received'] = ($c['received'] ?? 0) + $received;
                $status = $x->inspection_status ?: 'pending';
                $c['inspection'][$status] = ($c['inspection'][$status] ?? 0) + 1;
                // Approved: inspected lines that passed (fully or partly), less what was rejected.
                if (in_array($status, ['passed', 'partial'], true)) {
                    $c['approved'] = ($c['approved'] ?? 0) + max(0, $received - (float) $x->rejected_qty);
                }
            }));

        foreach ($out as &$c) {
            if (($c['expected'] ?? null) === '9999-12-31') {
                $c['expected'] = null;
            }
        }

        return $out;
    }

    /** One word for the GRN lines' inspection: Failed > Pending > Partly Passed > Passed. */
    private function inspectionText(array $counts): ?string
    {
        return match (true) {
            ! $counts => null,
            isset($counts['failed']) => 'Failed',
            isset($counts['pending']) => 'Pending',
            isset($counts['partial']) => 'Partly Passed',
            default => 'Passed',
        };
    }
}
