<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\PostCosting;
use ME\MerchandisingSfl\Support\Lookups;

/** Order + BOM → Post Cost Sheet: budget (approved cost sheet) vs actual (Inventory issues + production) per PO line. */
class PostCostingController extends Controller
{
    public function __construct(private readonly PostCosting $costing)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_post_costing.list');

        $pos = OrderPo::query()->with(['order.buyer', 'style', 'color'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['confirmed', 'closed'])
                ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id)))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('po_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))))
            ->latest('id')->paginate($this->perPage(20))->withQueryString();

        $rows = collect($pos->items())->map(fn ($po) => $this->costing->build($po));

        return view('merchandising-sfl::admin.post-costing.index', ['pos' => $pos, 'rows' => $rows, 'buyers' => Lookups::buyers()]);
    }

    public function show(OrderPo $po): View
    {
        $this->authorize('msfl_post_costing.view');

        return view('merchandising-sfl::admin.post-costing.show', ['c' => $this->costing->build($po)]);
    }

    public function print(OrderPo $po): View
    {
        $this->authorize('msfl_post_costing.view');

        return view('merchandising-sfl::admin.post-costing.print', ['c' => $this->costing->build($po)]);
    }
}
