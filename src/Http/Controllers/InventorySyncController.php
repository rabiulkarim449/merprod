<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\Buyer;
use ME\MerchandisingSfl\Services\InventorySync;

/** Pull existing Inventory Buyers / Styles (entered there first) into Merchandising's own masters. */
class InventorySyncController extends Controller
{
    public function __construct(private readonly InventorySync $sync)
    {
    }

    public function index(): View
    {
        $this->authorize('msfl_inventory_sync.view');

        $pendingStyles = $this->sync->pendingStyles();

        return view('merchandising-sfl::admin.inventory-sync.index', [
            'available' => $this->sync->available(),
            'pendingBuyers' => $this->sync->pendingBuyers(),
            'pendingStyles' => $pendingStyles,
            'buyerNames' => Buyer::query()->whereIn('id', $pendingStyles->pluck('buyer_id'))->pluck('name', 'id'),
        ]);
    }

    public function sync(): RedirectResponse
    {
        $this->authorize('msfl_inventory_sync.sync');

        $buyers = $this->sync->syncBuyers();
        $styles = $this->sync->syncStyles();

        $msg = count($buyers) . ' buyer(s) and ' . count($styles['created']) . ' style(s) synced from Inventory.';
        if ($styles['skipped']) {
            $msg .= ' ' . count($styles['skipped']) . ' style(s) skipped (style no. already used by another buyer): ' . implode(', ', $styles['skipped']) . '.';
        }

        return redirect()->route('msfl.inventory-sync.index')->with('success', $msg);
    }
}
