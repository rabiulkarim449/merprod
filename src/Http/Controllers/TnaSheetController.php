<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\TnaSheet;
use ME\MerchandisingSfl\Support\Lookups;

/** Planning → T&A Sheet: the buyer's 81-column sheet (one row per PO line), screen and print. */
class TnaSheetController extends Controller
{
    public function index(Request $request, TnaSheet $sheet): View
    {
        $this->authorize('msfl_tna.list');

        $pos = $this->query($request)->paginate(20)->withQueryString();
        $sheet->prepare($pos->getCollection());

        return view('merchandising-sfl::admin.tna-sheet.index', ['pos' => $pos, 'sheet' => $sheet, 'buyers' => Lookups::buyers()]);
    }

    public function print(Request $request, TnaSheet $sheet): View
    {
        $this->authorize('msfl_tna.view');

        $pos = $this->query($request)->limit(500)->get();
        $sheet->prepare($pos);

        return view('merchandising-sfl::admin.tna-sheet.print', [
            'pos' => $pos, 'sheet' => $sheet,
            'buyer' => $request->filled('buyer_id') ? \ME\MerchandisingSfl\Models\Buyer::find($request->buyer_id) : null,
        ]);
    }

    private function query(Request $request)
    {
        return OrderPo::query()
            ->whereHas('order', fn ($q) => $q->whereIn('status', $request->boolean('closed') ? ['confirmed', 'closed'] : ['confirmed'])
                ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
                ->when($request->filled('order_id'), fn ($q) => $q->whereKey($request->order_id)))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('po_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))))
            ->orderBy('shipment_date')->orderBy('id');
    }
}
