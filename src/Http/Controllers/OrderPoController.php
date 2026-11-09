<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Http\Requests\OrderPoRequest;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\OrderPoLines;

/**
 * One PO line at a time (kept for scripts such as the demo seeder) — the screen
 * edits all lines on the order form (OrderController + OrderPoLines::sync()).
 */
class OrderPoController extends Controller
{
    public function __construct(private readonly OrderPoLines $lines)
    {
    }

    public function store(OrderPoRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->isEditable(), 403);

        DB::transaction(function () use ($order, $request) {
            $this->lines->save($order, null, $request->validated());
            $order->refreshTotals();
        });

        return back()->with('success', 'PO line added.');
    }

    public function update(OrderPoRequest $request, Order $order, OrderPo $po): RedirectResponse
    {
        abort_unless((int) $po->order_id === (int) $order->id && $order->isEditable(), 403);

        DB::transaction(function () use ($order, $po, $request) {
            $this->lines->save($order, $po, $request->validated());
            $order->refreshTotals();
        });

        return back()->with('success', 'PO line updated.');
    }

    public function destroy(Order $order, OrderPo $po): RedirectResponse
    {
        $this->authorize('msfl_order.edit');
        abort_unless((int) $po->order_id === (int) $order->id && $order->isEditable(), 403);

        if ($reason = $this->lines->usedIn($po)) {
            return back()->with('error', $reason);
        }

        DB::transaction(function () use ($order, $po) {
            $po->delete();
            $order->refreshTotals();
        });

        return back()->with('success', 'PO line deleted.');
    }
}
