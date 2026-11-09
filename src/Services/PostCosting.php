<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\CostSheet;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * Post cost sheet of a PO line — Budget (the style's approved cost sheet,
 * per dozen × order qty) against Actual:
 *   material  = value Inventory actually issued against this PO (store
 *               rates, BDT → the cost sheet's currency by its exchange rate);
 *               estimated as budget material / pc × packed while that is 0;
 *   revenue   = packed pieces × FOB;
 *   CM earned = revenue − material − commercial (% of material) − process − other;
 *   reject loss = pieces rejected at any stage × budget material per piece.
 * Process (wash / print) and other cost have no actual source yet, so the
 * budget figure is carried into actual (marked).
 */
class PostCosting
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    public function costSheetFor(OrderPo $po): ?CostSheet
    {
        return CostSheet::query()->with('currency')->where('style_id', $po->style_id)->where('status', 'approved')->latest('id')->first();
    }

    public function build(OrderPo $po): array
    {
        $po->loadMissing(['order.buyer', 'order.currency', 'style', 'color']);
        $cs = $this->costSheetFor($po);
        $summary = $this->flow->summary($po);

        $qty = (int) $po->po_qty;
        $cut = $summary['cutting']['pass'];
        $packed = $summary['packing']['pass'];
        $rejects = (int) collect($summary)->sum('reject');
        $dz = fn ($perDozen, $pcs) => round((float) $perDozen * $pcs / 12, 2);
        $rate = max(0.0001, (float) ($cs?->currency->exchange_rate ?? 1)); // currency → BDT

        // Material actually issued from Inventory against this PO, less what was returned to store.
        $issued = collect();
        if (class_exists(\ME\SflInventory\Models\InvIssueReturnItem::class)) {
            $issued = DB::table('inv_issues as i')->join('inv_issue_items as ii', 'ii.issue_id', '=', 'i.id')
                ->leftJoinSub(\ME\SflInventory\Models\InvIssueReturnItem::totalsByIssueItem(), 'rtn', 'rtn.issue_item_id', '=', 'ii.id')
                ->join('inv_items as it', 'it.id', '=', 'ii.item_id')->leftJoin('inv_units as u', 'u.id', '=', 'it.unit_id')
                ->where('i.msfl_order_po_id', $po->id)->where('i.status', 'approved')->whereNull('i.deleted_at')
                ->groupBy('ii.item_id', 'it.item_name', 'u.short_name')
                ->selectRaw('it.item_name, u.short_name unit, SUM(ii.issued_qty - COALESCE(rtn.qty, 0)) qty, SUM(ii.amount - COALESCE(rtn.amount, 0)) amount')->get();
        }
        $materialBdt = (float) $issued->sum('amount');
        $hasRates = $materialBdt > 0;
        // No store value yet (nothing issued, or Inventory rates not set): estimate from the
        // budget material per piece so CM isn't overstated.
        $materialActual = $hasRates ? round($materialBdt / $rate, 2) : ($cs ? $dz($cs->materialCost(), $packed) : 0.0);

        $budget = null;
        if ($cs) {
            $budget = [
                'fabric' => $dz($cs->fabric_cost, $qty), 'trims' => $dz($cs->trims_cost, $qty), 'process' => $dz($cs->process_cost, $qty),
                'commercial' => $dz($cs->commercial_cost, $qty), 'other' => $dz($cs->other_cost, $qty),
                'cm_costsheet' => $dz($cs->cm_cost, $qty), 'revenue' => round((float) $po->unit_price * $qty, 2),
            ];
            // CM the same way as actual: FOB − material − commercial − process − other.
            $budget['cm'] = round($budget['revenue'] - $budget['fabric'] - $budget['trims'] - $budget['commercial'] - $budget['process'] - $budget['other'], 2);
        }

        $revenue = round((float) $po->unit_price * $packed, 2);
        $commercial = $cs ? round($materialActual * (float) $cs->commercial_percent / 100, 2) : 0;
        $process = $cs ? $dz($cs->process_cost, $packed) : 0;
        $other = $cs ? $dz($cs->other_cost, $packed) : 0;
        $materialPerPc = $cs ? (float) $cs->materialCost() / 12 : 0;

        return [
            'po' => $po, 'costSheet' => $cs, 'summary' => $summary,
            'qty' => ['order' => $qty, 'cut' => $cut, 'packed' => $packed, 'rejects' => $rejects,
                'cut_to_pack' => $cut > 0 ? round($packed / $cut * 100, 1) : null, 'order_to_pack' => $qty > 0 ? round($packed / $qty * 100, 1) : null],
            'budget' => $budget,
            'actual' => [
                'material' => $materialActual, 'material_bdt' => $materialBdt, 'has_rates' => $hasRates,
                'commercial' => $commercial, 'process' => $process, 'other' => $other, 'revenue' => $revenue,
                'cm_earned' => round($revenue - $materialActual - $commercial - $process - $other, 2),
                'reject_loss' => round($rejects * $materialPerPc, 2),
            ],
            'issued' => $issued,
            'currency' => $cs?->currency->code ?? ($po->order->currency->code ?? ''),
        ];
    }
}
