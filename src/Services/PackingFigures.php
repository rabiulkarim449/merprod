<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Size;

/**
 * Size-wise figures of a PO line for the packing lists:
 *   order    = PO size breakdown
 *   cut      = cutting of that size
 *   packed   = production packing pass of that size (ProductionFlow) — what commercial can ship
 *   invoiced = pcs of that size on commercial invoice cartons (carton pcs × cartons)
 * and QC's reason text for a short / excess (Buyer QC rejects by defect).
 */
class PackingFigures
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    /**
     * @return array{sizes: Collection<int, Size>, order: array<int,int>, cut: array<int,int>, packed: array<int,int>, invoiced: array<int,int>}
     */
    public function forPo(OrderPo $po, ?int $ignoreInvoiceId = null): array
    {
        $po->loadMissing('sizes.size');
        $sizes = Size::displaySort($po->sizes->pluck('size')->filter());

        $cut = DB::table('msfl_prod_cutting_sizes as s')->join('msfl_prod_cuttings as c', 'c.id', '=', 's.cutting_id')
            ->whereNull('c.deleted_at')->where('c.order_po_id', $po->id)
            ->groupBy('s.size_id')->selectRaw('s.size_id, SUM(s.qty) q')->pluck('q', 'size_id');

        $packed = [];
        foreach ($sizes as $size) {
            $packed[$size->id] = (int) ($this->flow->summary($po, null, $size->id)['packing']['pass'] ?? 0);
        }

        return [
            'sizes' => $sizes,
            'order' => $po->sizes->pluck('qty', 'size_id')->map(fn ($q) => (int) $q)->all(),
            'cut' => $cut->map(fn ($q) => (int) $q)->all(),
            'packed' => $packed,
            'invoiced' => $this->invoicedBySize($po->id, $ignoreInvoiceId),
        ];
    }

    /**
     * After packing, per PO line of a final packing list: packed (production) → received in the
     * Finish Store (Inventory FG receive) → on a shipment (Inventory) → on a commercial invoice.
     */
    public function afterPacking(Collection $pos): array
    {
        $ids = $pos->pluck('id')->all();
        $fg = Schema::hasTable('inv_finished_goods_receive_items') ? DB::table('inv_finished_goods_receives as r')
            ->join('inv_finished_goods_receive_items as ri', 'ri.fg_receive_id', '=', 'r.id')->whereNull('r.deleted_at')
            ->whereIn('r.msfl_order_po_id', $ids)->groupBy('r.msfl_order_po_id')->selectRaw('r.msfl_order_po_id po, SUM(ri.quantity) q')->pluck('q', 'po') : collect();
        $shipped = Schema::hasTable('inv_shipment_items') ? DB::table('inv_shipment_items as si')->join('inv_shipments as s', 's.id', '=', 'si.shipment_id')
            ->whereNull('s.deleted_at')->whereIn('si.msfl_order_po_id', $ids)->groupBy('si.msfl_order_po_id')->selectRaw('si.msfl_order_po_id po, SUM(si.quantity) q')->pluck('q', 'po') : collect();
        $invoiced = DB::table('msfl_com_invoice_lines as l')->join('msfl_com_invoices as i', 'i.id', '=', 'l.invoice_id')->whereNull('i.deleted_at')
            ->whereIn('l.order_po_id', $ids)->groupBy('l.order_po_id')->selectRaw('l.order_po_id po, SUM(l.qty) q')->pluck('q', 'po');

        return $pos->map(fn (OrderPo $po) => [
            'po' => $po,
            'packed' => (int) $this->flow->summary($po)['packing']['pass'],
            'fg' => (int) ($fg[$po->id] ?? 0),
            'shipped' => (int) ($shipped[$po->id] ?? 0),
            'invoiced' => (int) ($invoiced[$po->id] ?? 0),
        ])->values()->all();
    }

    /** size_id => pcs of a PO on invoice cartons (invoices not deleted, optionally leaving one out). */
    public function invoicedBySize(int $poId, ?int $ignoreInvoiceId = null): array
    {
        return DB::table('msfl_com_invoice_carton_sizes as cs')
            ->join('msfl_com_invoice_cartons as c', 'c.id', '=', 'cs.invoice_carton_id')
            ->join('msfl_com_invoices as i', 'i.id', '=', 'c.invoice_id')
            ->whereNull('i.deleted_at')->where('c.order_po_id', $poId)
            ->when($ignoreInvoiceId, fn ($q) => $q->where('i.id', '!=', $ignoreInvoiceId))
            ->groupBy('cs.size_id')->selectRaw('cs.size_id, SUM(cs.qty * (c.carton_to - c.carton_from + 1)) q')
            ->pluck('q', 'size_id')->map(fn ($q) => (int) $q)->all();
    }

    /** "2 pcs reject at Buyer QC (Stain 1, Open seam 1)" — or null when Buyer QC rejected nothing. */
    public function qcNote(OrderPo $po): ?string
    {
        $defects = DB::table('msfl_prod_entry_defects as d')->join('msfl_prod_entries as e', 'e.id', '=', 'd.entry_id')
            ->whereNull('e.deleted_at')->where('e.order_po_id', $po->id)->where('e.stage', 'final_qc')->where('d.type', 'reject')
            ->groupBy('d.defect')->selectRaw('d.defect, SUM(d.qty) q')->pluck('q', 'defect');
        $total = (int) $defects->sum();

        return $total > 0
            ? $total . ' pcs reject at Buyer QC (' . $defects->map(fn ($q, $d) => ($d ?: 'other') . ' ' . (int) $q)->implode(', ') . ')'
            : null;
    }

    /**
     * The whole Final Packing List sheet (what the print shows / what approval freezes):
     * header, common size columns, and per style line order / cut / ship per size + note.
     *
     * @param array<int,string> $notes order_po_id => note typed by QC (else the Buyer QC reject note)
     */
    public function finalSheet(\ME\MerchandisingSfl\Models\Production\FinalPackingList $fpl, array $notes = []): array
    {
        $fpl->loadMissing('order.buyer');
        $pos = $fpl->poLines()->with(['style', 'color', 'sizes.size'])->get()->sortBy(fn ($p) => $p->style->style_no ?? '')->values();
        $figures = $pos->mapWithKeys(fn ($po) => [$po->id => $this->forPo($po)]);
        $sizes = Size::displaySort($pos->flatMap(fn ($p) => $p->sizes->pluck('size'))->filter()->unique('id'));

        $lines = $pos->map(function (OrderPo $po) use ($figures, $sizes, $notes) {
            $f = $figures[$po->id];
            $row = fn (array $bySize) => collect($sizes)->mapWithKeys(fn ($s) => [$s->id => (int) ($bySize[$s->id] ?? 0)])->all();
            $order = $row($f['order']);
            $cut = $row($f['cut']);
            $ship = $row($f['packed']);

            return [
                'po_id' => $po->id, 'style_no' => $po->style->style_no ?? '', 'style_name' => $po->style->name ?? '', 'color' => $po->color->name ?? '',
                'order' => $order, 'cut' => $cut, 'ship' => $ship,
                'order_total' => array_sum($order), 'cut_total' => array_sum($cut), 'ship_total' => array_sum($ship),
                'short' => array_sum($ship) - array_sum($order),
                'note' => $notes[$po->id] ?? $this->qcNote($po),
            ];
        });
        $join = fn (string $key) => $lines->pluck($key)->implode('+') . ($lines->count() > 1 ? '=' . $lines->sum($key) : '') . ' Pcs';

        return [
            'buyer' => $fpl->order->buyer->name ?? '',
            'po_no' => $fpl->po_no,
            'order_no' => $fpl->order->order_no ?? '',
            'styles' => $lines->pluck('style_no')->unique()->implode(' & '),
            'description' => $lines->pluck('style_name')->unique()->implode(' / '),
            'invoices' => DB::table('msfl_com_invoice_lines as l')->join('msfl_com_invoices as i', 'i.id', '=', 'l.invoice_id')->whereNull('i.deleted_at')
                ->whereIn('l.order_po_id', $pos->pluck('id'))->distinct()->orderBy('i.invoice_no')->pluck('i.invoice_no')->implode(', '),
            'order_qty' => $join('order_total'), 'cut_qty' => $join('cut_total'), 'ship_qty' => $join('ship_total'),
            'sizes' => $sizes->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()->all(),
            'lines' => $lines->all(),
        ];
    }
}
