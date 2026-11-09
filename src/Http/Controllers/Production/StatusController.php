<?php

namespace ME\MerchandisingSfl\Http\Controllers\Production;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Cutting;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Production\FabricRequisition;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Support\Lookups;

/** Production → Status: every PO's pieces at each stage, and one PO's full history. */
class StatusController extends Controller
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_status.list');

        $pos = OrderPo::query()
            ->with(['order.buyer', 'style', 'color'])
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed')
                ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id)))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('po_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))
                ->orWhereHas('order', fn ($q) => $q->where('order_no', 'like', '%' . $request->search . '%'))))
            ->latest('id')->paginate($this->perPage(20))->withQueryString();

        return view('merchandising-sfl::admin.production.status.index', [
            'pos' => $pos,
            'summaries' => $this->flow->summaries(collect($pos->items())),
            'buyers' => Lookups::buyers(),
        ]);
    }

    public function show(OrderPo $po): View
    {
        $this->authorize('msfl_prod_status.list');

        $po->load(['order.buyer', 'style', 'color', 'sizes.size']);

        return view('merchandising-sfl::admin.production.status.show', [
            'po' => $po,
            'summary' => $this->flow->summary($po),
            'cuttings' => Cutting::query()->where('order_po_id', $po->id)->with('sizes.size')->latest('cutting_date')->get(),
            'entries' => Entry::query()->where('order_po_id', $po->id)->with(['size', 'line.floorLine', 'defects'])->latest('entry_date')->latest('id')->get(),
            'requisitions' => class_exists(\ME\SflInventory\Models\InvRequisition::class)
                ? FabricRequisition::query()->where('order_po_id', $po->id)->with(['requisition.store', 'requisition.items.item'])->latest('id')->get()
                : collect(),
        ]);
    }
}
