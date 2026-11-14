<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\Buyer;
use ME\MerchandisingSfl\Services\InventorySync;

/** Pull existing Inventory Buyers / Styles / Items (entered there first) into Merchandising's own masters. */
class InventorySyncController extends Controller
{
    public function __construct(private readonly InventorySync $sync)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_inventory_sync.view');

        $pendingStyles = $this->sync->pendingStyles();
        $categories = $this->sync->itemCategories();
        // Ticked categories: the ones picked on this page, else the BOM-type ones.
        $picked = $request->has('cats') ? array_map('intval', (array) $request->input('cats')) : $categories->where('default', true)->pluck('id')->all();

        return view('merchandising-sfl::admin.inventory-sync.index', [
            'available' => $this->sync->available(),
            'pendingBuyers' => $this->sync->pendingBuyers(),
            'pendingStyles' => $pendingStyles,
            'buyerNames' => Buyer::query()->whereIn('id', $pendingStyles->pluck('buyer_id'))->pluck('name', 'id'),
            'itemCategories' => $categories,
            'pickedCategories' => $picked,
            'pendingItems' => $this->sync->pendingItems($picked),
        ]);
    }

    /** Store items of the ticked categories → Merchandising items linked to them. */
    public function syncItems(Request $request): RedirectResponse
    {
        $this->authorize('msfl_inventory_sync.sync');
        $cats = array_map('intval', (array) $request->validate(['cats' => ['required', 'array', 'min:1'], 'cats.*' => ['integer']])['cats']);

        $result = $this->sync->syncItems($cats);

        return redirect()->route('msfl.inventory-sync.index', ['cats' => $cats])
            ->with('success', "{$result['created']} item(s) created and {$result['linked']} existing item(s) linked to their store item.");
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
