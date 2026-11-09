<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\OrderRequest;
use ME\MerchandisingSfl\Models\Inquiry;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Models\TnaPlan;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Services\OrderPoLines;
use ME\MerchandisingSfl\Support\Autofill;
use ME\MerchandisingSfl\Support\Lookups;

/** Order + BOM — buyer order header and its PO lines, entered together on one page. */
class OrderController extends Controller
{
    public function __construct(private readonly FileUploadService $files, private readonly OrderPoLines $lines)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_order.list');

        $orders = Order::query()
            ->with(['buyer', 'season', 'merchandiser', 'currency'])
            ->withCount('pos')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('order_no', 'like', '%' . $request->search . '%')
                ->orWhere('buyer_order_ref', 'like', '%' . $request->search . '%')
                ->orWhereHas('pos', fn ($q) => $q->where('po_no', 'like', '%' . $request->search . '%')
                    ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%')
                        ->orWhere('name', 'like', '%' . $request->search . '%')))))
            ->when($request->filled('style'), fn ($q) => $q->whereHas('pos.style', fn ($q) => $q
                ->where('style_no', 'like', '%' . trim($request->style) . '%')
                ->orWhere('name', 'like', '%' . trim($request->style) . '%')))
            ->when($request->filled('po_no'), fn ($q) => $q->whereHas('pos', fn ($q) => $q->where('po_no', 'like', '%' . trim($request->po_no) . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('is_quick_entry'), fn ($q) => $q->where('is_quick_entry', $request->boolean('is_quick_entry')))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $buyers = Lookups::buyers();

        return view('merchandising-sfl::admin.orders.index', compact('orders', 'buyers'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_order.add');

        $order = new Order(['order_date' => now(), 'merchandiser_id' => auth()->id()]);
        if ($inquiry = Inquiry::find($request->integer('inquiry_id'))) {
            $order->fill($inquiry->only(['buyer_id', 'season_id', 'merchandiser_id', 'factory_id']) + ['inquiry_id' => $inquiry->id]);
        }

        return view('merchandising-sfl::admin.orders.create', $this->formData() + $this->poFormData($order) + compact('order'));
    }

    public function store(OrderRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['attachment', 'pos']);
        $data['attachment'] = $this->files->store($request->file('attachment'), 'orders');

        $order = DB::transaction(function () use ($data, $numbers, $request) {
            $order = Order::create($data + [
                'order_no' => $numbers->next('order', Order::class, 'order_no'),
                'created_by' => auth()->id(),
            ]);
            $this->lines->sync($order, $request->poLines());

            return $order;
        });

        return redirect()->route('msfl.orders.show', $order)->with('success', 'Order ' . $order->order_no . ' created with ' . $order->pos()->count() . ' PO line(s).');
    }

    public function show(Order $order): View
    {
        $this->authorize('msfl_order.view');

        $order->load(['buyer', 'season', 'merchandiser', 'factory', 'inquiry', 'currency', 'confirmer', 'boms.style']);
        $tnaPlans = TnaPlan::where('order_id', $order->id)->where('status', '!=', 'cancelled')->get()->keyBy('style_id');

        return view('merchandising-sfl::admin.orders.show', $this->poLinesData($order) + compact('order', 'tnaPlans'));
    }

    /** The PO lines block of the order form (orders/partials/po-form): sizes, styles of every buyer, colors, ship modes. */
    private function poFormData(Order $order): array
    {
        $usedSizeIds = $order->exists ? $order->pos()->with('sizes')->get()->flatMap(fn ($po) => $po->sizes->pluck('size_id'))->unique()->values() : collect();

        return [
            'formSizes' => Size::displaySort(Size::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $usedSizeIds))->get()),
            // Sizes this order uses (size columns of the PO lines); an edit starts with the ones already used.
            'selectedSizeIds' => collect(old('size_ids', $usedSizeIds->all()))->map(fn ($id) => (int) $id)->all(),
            'styles' => Lookups::styles(),
            'colors' => Lookups::colors(),
            'shipModes' => Lookups::shipModes(),
            // Style picked in a row → the agreed price (cost sheet, else inquiry) and the inquiry's ship date.
            'styleFill' => collect(Autofill::styles())->map(fn ($s) => array_intersect_key($s, array_flip(['unit_price', 'shipment_date']))),
        ];
    }

    /** What the read-only PO lines table needs (orders/partials/po-lines). */
    private function poLinesData(Order $order): array
    {
        $order->load(['pos' => fn ($q) => $q->orderBy('po_no')->orderBy('style_id'), 'pos.style', 'pos.color', 'pos.shipMode', 'pos.sizes']);
        $usedSizeIds = $order->pos->flatMap(fn ($po) => $po->sizes->pluck('size_id'))->unique();

        return ['sizeColumns' => Size::displaySort(Size::query()->whereIn('id', $usedSizeIds)->get())];
    }

    public function edit(Order $order): View|RedirectResponse
    {
        $this->authorize('msfl_order.edit');

        if (! $order->isEditable()) {
            return redirect()->route('msfl.orders.show', $order)->with('error', 'A ' . $order->statusLabel() . ' order cannot be edited');
        }

        $order->load(['pos' => fn ($q) => $q->orderBy('id'), 'pos.sizes']);

        return view('merchandising-sfl::admin.orders.edit', $this->formData() + $this->poFormData($order) + compact('order'));
    }

    public function update(OrderRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->isEditable(), 403);

        $data = Arr::except($request->validated(), ['attachment', 'pos']);
        $data['attachment'] = $this->files->store($request->file('attachment'), 'orders', $order->attachment);

        DB::transaction(function () use ($order, $data, $request) {
            $order->update($data);
            $this->lines->sync($order, $request->poLines());
        });

        return redirect()->route('msfl.orders.show', $order)->with('success', 'Order updated successfully.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->authorize('msfl_order.delete');

        if ($order->status !== 'draft') {
            return back()->with('error', 'Only a draft order can be deleted — cancel it instead');
        }

        $order->delete();

        return redirect()->route('msfl.orders.index')->with('success', 'Order deleted successfully.');
    }

    /** Confirm / Close / Cancel. */
    public function changeStatus(Request $request, Order $order): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(Order::STATUSES))]])['status'];
        $this->authorize($status === 'confirmed' ? 'msfl_order.approve' : 'msfl_order.edit');

        if (! $order->canMoveTo($status)) {
            return back()->with('error', 'A ' . $order->statusLabel() . ' order cannot be changed to ' . Order::STATUSES[$status][0]);
        }

        if ($status === 'confirmed' && ! $order->pos()->exists()) {
            return back()->with('error', 'Add at least one PO line before confirming the order');
        }

        $order->forceFill(['status' => $status] + ($status === 'confirmed' ? ['confirmed_by' => auth()->id(), 'confirmed_at' => now()] : []))->save();

        return back()->with('success', 'Order ' . strtolower(Order::STATUSES[$status][0]) . '.');
    }

    private function formData(): array
    {
        return [
            'buyers' => Lookups::buyers(),
            'seasons' => Lookups::seasons(),
            'merchandisers' => Lookups::merchandisers(),
            'factories' => Lookups::factories(),
            'inquiries' => Lookups::inquiries(),
            'currencies' => Lookups::currencies(),
        ];
    }
}
