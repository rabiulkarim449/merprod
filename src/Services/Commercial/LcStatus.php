<?php

namespace ME\MerchandisingSfl\Services\Commercial;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\ProductionFlow;

/**
 * Where an Export LC stands:
 *   PO value  = Σ PO qty × FOB of the PO lines on the LC
 *   shipped   = Σ commercial invoice lines (invoices not deleted)
 *   balance   = LC value − shipped;  limit = LC value + tolerance
 * and per PO: order qty, packed (production), invoiced, can still invoice (packed − invoiced).
 */
class LcStatus
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    /** po_id => qty already on commercial invoices (optionally leaving one invoice out). */
    public function invoicedQty($poIds, ?int $ignoreInvoiceId = null): array
    {
        return DB::table('msfl_com_invoice_lines as l')->join('msfl_com_invoices as i', 'i.id', '=', 'l.invoice_id')
            ->whereNull('i.deleted_at')->whereIn('l.order_po_id', $poIds)
            ->when($ignoreInvoiceId, fn ($q) => $q->where('i.id', '!=', $ignoreInvoiceId))
            ->groupBy('l.order_po_id')->selectRaw('l.order_po_id po, SUM(l.qty) q')->pluck('q', 'po')
            ->map(fn ($q) => (int) $q)->all();
    }

    /** Value shipped on the LC's invoices (optionally leaving one invoice out). */
    public function shippedValue(ExportLc $lc, ?int $ignoreInvoiceId = null): float
    {
        return (float) DB::table('msfl_com_invoices')->whereNull('deleted_at')->where('export_lc_id', $lc->id)
            ->when($ignoreInvoiceId, fn ($q) => $q->where('id', '!=', $ignoreInvoiceId))->sum('total_value');
    }

    /** @return array{po_value: float, shipped: float, balance: float, limit: float, invoices: int, expiry_days: ?int, last_ship_days: ?int} */
    public function figures(ExportLc $lc): array
    {
        $lc->loadMissing('pos');
        $shipped = $this->shippedValue($lc);

        return [
            'po_value' => round($lc->pos->sum(fn ($p) => (int) $p->po_qty * (float) $p->unit_price), 2),
            'shipped' => round($shipped, 2),
            'balance' => round((float) $lc->lc_value - $shipped, 2),
            'limit' => $lc->maxValue(),
            'invoices' => $lc->invoices()->count(),
            'expiry_days' => $lc->expiry_date ? (int) today()->diffInDays($lc->expiry_date, false) : null,
            'last_ship_days' => $lc->last_shipment_date ? (int) today()->diffInDays($lc->last_shipment_date, false) : null,
        ];
    }

    /**
     * Per PO of the LC: order qty / value, packed, invoiced, can still invoice.
     *
     * @return Collection<int, array{po: OrderPo, qty: int, value: float, packed: int, invoiced: int, available: int}>
     */
    public function poRows(ExportLc $lc, ?int $ignoreInvoiceId = null): Collection
    {
        $lc->loadMissing(['pos.style', 'pos.color', 'pos.order']);
        $invoiced = $this->invoicedQty($lc->pos->pluck('id'), $ignoreInvoiceId);

        return $lc->pos->sortBy(fn ($p) => $p->po_no . '|' . ($p->style->style_no ?? ''))->values()->map(function (OrderPo $po) use ($invoiced) {
            $packed = (int) ($this->flow->summary($po)['packing']['pass'] ?? 0);
            $inv = (int) ($invoiced[$po->id] ?? 0);

            return [
                'po' => $po, 'qty' => (int) $po->po_qty, 'value' => round((int) $po->po_qty * (float) $po->unit_price, 2),
                'packed' => $packed, 'invoiced' => $inv, 'available' => max(0, $packed - $inv),
            ];
        });
    }

    /** Confirmed POs of a buyer not on another LC (plus the ones already on $lc). */
    public function selectablePos(int $buyerId, ?ExportLc $lc = null): Collection
    {
        $taken = DB::table('msfl_com_export_lc_pos as p')->join('msfl_com_export_lcs as l', 'l.id', '=', 'p.export_lc_id')
            ->whereNull('l.deleted_at')->when($lc?->exists, fn ($q) => $q->where('l.id', '!=', $lc->id))->pluck('p.order_po_id');

        return OrderPo::query()->with(['order', 'style', 'color'])
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed')->where('buyer_id', $buyerId))
            ->whereNotIn('id', $taken)->orderBy('shipment_date')->orderBy('po_no')->get();
    }
}
