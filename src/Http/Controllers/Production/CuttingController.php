<?php

namespace ME\MerchandisingSfl\Http\Controllers\Production;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Cutting;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → Cutting: pieces cut per size for an order PO (buyer, style
 * and color come from the PO), parts cut per size, and bundles of N pieces made
 * automatically per size.
 */
class CuttingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_cutting.list');

        $cuttings = Cutting::query()
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('cutting_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('orderPo', fn ($q) => $q->where('po_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->latest('id')->paginate($this->perPage(20))->withQueryString();

        return view('merchandising-sfl::admin.production.cuttings.index', ['cuttings' => $cuttings, 'pos' => Lookups::productionPos()]);
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_prod_cutting.add');

        $pos = Lookups::productionPos()->load('sizes');
        $cutBySize = Cutting::query()->join('msfl_prod_cutting_sizes as cs', 'cs.cutting_id', '=', 'msfl_prod_cuttings.id')
            ->whereNull('msfl_prod_cuttings.deleted_at')
            ->selectRaw('order_po_id, cs.size_id, SUM(cs.qty) qty')->groupBy('order_po_id', 'cs.size_id')->get()
            ->groupBy('order_po_id')->map(fn ($rows) => $rows->pluck('qty', 'size_id'));

        return view('merchandising-sfl::admin.production.cuttings.create', [
            'pos' => $pos,
            'cutBySize' => $cutBySize,
            'sizes' => Size::query()->get(['id', 'name']),
            'garmentParts' => Lookups::garmentParts(),
            'selectedPo' => $request->integer('order_po_id') ?: null,
        ]);
    }

    public function store(Request $request, DocumentNumberService $numbers): RedirectResponse
    {
        $this->authorize('msfl_prod_cutting.add');

        $data = $request->validate([
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'cutting_date' => ['required', 'date', 'before_or_equal:today'],
            'table_no' => ['nullable', 'string', 'max:50'],
            'lay_count' => ['nullable', 'integer', 'min:0'],
            'fabric_used' => ['nullable', 'numeric', 'min:0'],
            'bundle_size' => ['required', 'integer', 'min:1', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'sizes' => ['required', 'array'],
            'sizes.*' => ['nullable', 'integer', 'min:0'],
            'parts' => ['nullable', 'array'],
            'parts.*.part_name' => ['nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'parts.*.sizes' => ['nullable', 'array'],
            'parts.*.sizes.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $po = OrderPo::with('order')->findOrFail($data['order_po_id']);
        if (($po->order->status ?? null) !== 'confirmed') {
            throw ValidationException::withMessages(['order_po_id' => 'Only a confirmed order can go into production.']);
        }

        $sizes = collect($data['sizes'])->map(fn ($q) => (int) $q)->filter();
        if ($sizes->isEmpty()) {
            throw ValidationException::withMessages(['sizes' => 'Enter the cut quantity for at least one size.']);
        }
        // Parts cut per size: part => [size_id => qty], only for sizes cut here.
        $parts = collect($data['parts'] ?? [])->filter(fn ($p) => filled($p['part_name'] ?? null))
            ->map(fn ($p) => ['part_name' => trim($p['part_name']), 'sizes' => collect($p['sizes'] ?? [])->map(fn ($q) => (int) $q)->filter()])
            ->filter(fn ($p) => $p['sizes']->isNotEmpty());
        if ($parts->contains(fn ($p) => $p['sizes']->keys()->diff($sizes->keys())->isNotEmpty())) {
            throw ValidationException::withMessages(['parts' => 'Parts can only be entered for sizes cut in this cutting.']);
        }

        $cutting = DB::transaction(function () use ($data, $sizes, $parts, $numbers, $po) {
            // Per size: everything cut so far plus this cutting stays within the order qty + the excess allowance.
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();
            $ordered = $po->sizes()->pluck('qty', 'size_id');
            $cutSoFar = DB::table('msfl_prod_cutting_sizes as s')->join('msfl_prod_cuttings as c', 'c.id', '=', 's.cutting_id')
                ->whereNull('c.deleted_at')->where('c.order_po_id', $po->id)->groupBy('s.size_id')->selectRaw('s.size_id, SUM(s.qty) q')->pluck('q', 'size_id');
            $excess = (float) config('merchandising-sfl.cutting_excess_percent', 5);
            $names = Size::query()->whereIn('id', $sizes->keys())->pluck('name', 'id');
            foreach ($sizes as $sizeId => $qty) {
                $name = $names[$sizeId] ?? $sizeId;
                if (! isset($ordered[$sizeId])) {
                    throw ValidationException::withMessages(["sizes.{$sizeId}" => "Size {$name} is not in this PO."]);
                }
                $max = (int) floor((int) $ordered[$sizeId] * (1 + $excess / 100));
                $after = (int) ($cutSoFar[$sizeId] ?? 0) + $qty;
                if ($after > $max) {
                    throw ValidationException::withMessages(["sizes.{$sizeId}" => "Size {$name}: order {$ordered[$sizeId]} pcs (+{$excess}% allowed = {$max}), already cut " . (int) ($cutSoFar[$sizeId] ?? 0)
                        . ', so only ' . max(0, $max - (int) ($cutSoFar[$sizeId] ?? 0)) . " more can be cut (you entered {$qty})."]);
                }
            }

            $cutting = Cutting::create([
                'cutting_no' => $numbers->next('cutting', Cutting::class, 'cutting_no'),
                'total_qty' => $sizes->sum(),
                'created_by' => auth()->id(),
            ] + collect($data)->only(['order_po_id', 'cutting_date', 'table_no', 'lay_count', 'fabric_used', 'bundle_size', 'remarks'])->all());

            $n = 1;
            foreach ($sizes as $sizeId => $qty) {
                $cutting->sizes()->create(['size_id' => $sizeId, 'qty' => $qty]);
                // Bundles of bundle_size pieces; the last one takes the remainder.
                for ($left = $qty; $left > 0; $left -= $data['bundle_size']) {
                    $cutting->bundles()->create([
                        'bundle_no' => $cutting->cutting_no . '-' . str_pad((string) $n++, 3, '0', STR_PAD_LEFT),
                        'size_id' => $sizeId,
                        'qty' => min($left, $data['bundle_size']),
                    ]);
                }
            }
            foreach ($parts as $part) {
                foreach ($part['sizes'] as $sizeId => $qty) {
                    $cutting->parts()->create(['part_name' => $part['part_name'], 'size_id' => $sizeId, 'qty' => $qty]);
                }
            }

            return $cutting;
        });

        return redirect()->route('msfl.production.cuttings.show', $cutting)
            ->with('success', "Cutting {$cutting->cutting_no} saved — {$cutting->total_qty} pcs in {$cutting->bundles()->count()} bundles.");
    }

    public function show(Cutting $cutting): View
    {
        $this->authorize('msfl_prod_cutting.view');

        $cutting->load(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'sizes.size', 'parts.size', 'bundles.size', 'creator']);

        return view('merchandising-sfl::admin.production.cuttings.show', compact('cutting'));
    }

    public function destroy(Cutting $cutting): RedirectResponse
    {
        $this->authorize('msfl_prod_cutting.delete');

        // Removing pieces that a later stage already took in would leave it short.
        $flow = app(\ME\MerchandisingSfl\Services\ProductionFlow::class);
        $po = $cutting->orderPo;
        $next = $flow->route($po)[1];
        $taken = $flow->summary($po)[$next]['input'];
        $remainingCut = $flow->summary($po)['cutting']['pass'] - $cutting->total_qty;
        if ($taken > $remainingCut) {
            return back()->with('error', "{$taken} pcs of this PO are already in " . $flow::label($next) . " — this cutting can't be deleted.");
        }

        $cutting->delete();

        return redirect()->route('msfl.production.cuttings.index')->with('success', "Cutting {$cutting->cutting_no} deleted.");
    }
}
