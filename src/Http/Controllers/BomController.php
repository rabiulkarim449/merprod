<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\BomRequest;
use ME\MerchandisingSfl\Models\Bom;
use ME\MerchandisingSfl\Models\Item;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Support\Lookups;

/** Order + BOM — bill of materials per style, optionally against an order. */
class BomController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_bom.list');

        $boms = Bom::query()
            ->with(['style.buyer', 'order'])
            ->withCount('items')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('bom_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('order_id'), fn ($q) => $q->where('order_id', $request->order_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $orders = Lookups::orders();

        return view('merchandising-sfl::admin.boms.index', compact('boms', 'orders'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_bom.add');

        $bom = new Bom([
            'style_id' => $request->integer('style_id') ?: null,
            'order_id' => $request->integer('order_id') ?: null,
            'bom_type' => 'manual',
        ]);

        return view('merchandising-sfl::admin.boms.create', $this->formData() + compact('bom'));
    }

    public function store(BomRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = $request->validated();

        $bom = DB::transaction(function () use ($request, $data, $numbers) {
            $bom = Bom::create([
                'bom_no' => $numbers->next('bom', Bom::class, 'bom_no'),
                'style_id' => $data['style_id'],
                'order_id' => $data['order_id'] ?? null,
                'version' => $this->nextVersion($data['style_id'], $data['order_id'] ?? null),
                'bom_type' => $data['bom_type'],
                'bom_file' => $this->files->store($request->file('bom_file'), 'boms'),
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $this->syncItems($bom, $data['items'] ?? []);

            return $bom;
        });

        return redirect()->route('msfl.boms.show', $bom)->with('success', 'BOM ' . $bom->bom_no . ' created successfully.');
    }

    public function show(Bom $bom): View
    {
        $this->authorize('msfl_bom.view');

        $bom->load(['style.buyer', 'order.pos.sizes', 'approver', 'items.item', 'items.color', 'items.size', 'items.uom', 'items.supplier']);

        return view('merchandising-sfl::admin.boms.show', compact('bom'));
    }

    public function edit(Bom $bom): View|RedirectResponse
    {
        $this->authorize('msfl_bom.edit');

        if (! $bom->isEditable()) {
            return redirect()->route('msfl.boms.show', $bom)->with('error', 'An approved BOM cannot be edited — use Revise to make a new version');
        }

        $bom->load('items');

        return view('merchandising-sfl::admin.boms.edit', $this->formData() + compact('bom'));
    }

    public function update(BomRequest $request, Bom $bom): RedirectResponse
    {
        abort_unless($bom->isEditable(), 403);
        $data = $request->validated();

        DB::transaction(function () use ($request, $bom, $data) {
            $bom->update([
                'style_id' => $data['style_id'],
                'order_id' => $data['order_id'] ?? null,
                'bom_type' => $data['bom_type'],
                'bom_file' => $this->files->store($request->file('bom_file'), 'boms', $bom->bom_file),
                'remarks' => $data['remarks'] ?? null,
            ]);
            $this->syncItems($bom, $data['items'] ?? []);
        });

        return redirect()->route('msfl.boms.show', $bom)->with('success', 'BOM updated successfully.');
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        $this->authorize('msfl_bom.delete');

        if (! $bom->isEditable()) {
            return back()->with('error', 'An approved BOM cannot be deleted');
        }

        $bom->delete();

        return redirect()->route('msfl.boms.index')->with('success', 'BOM deleted successfully.');
    }

    public function approve(Bom $bom): RedirectResponse
    {
        $this->authorize('msfl_bom.approve');
        abort_unless($bom->isEditable(), 403);

        if ($bom->bom_type === 'manual' && ! $bom->items()->exists()) {
            return back()->with('error', 'Add item lines before approving the BOM');
        }

        $bom->forceFill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();

        return back()->with('success', 'BOM approved.');
    }

    /** Copy an approved BOM into a new draft version. */
    public function revise(Bom $bom, DocumentNumberService $numbers): RedirectResponse
    {
        $this->authorize('msfl_bom.add');

        $revision = DB::transaction(function () use ($bom, $numbers) {
            $revision = $bom->replicate(['bom_no', 'version', 'status', 'approved_by', 'approved_at']);
            $revision->forceFill([
                'bom_no' => $numbers->next('bom', Bom::class, 'bom_no'),
                'version' => $this->nextVersion($bom->style_id, $bom->order_id),
                'status' => 'draft',
                // Own copy of the buyer file — replacing it on the revision must not delete the original's.
                'bom_file' => $this->files->copy($bom->bom_file, 'boms'),
                'created_by' => auth()->id(),
            ])->save();

            foreach ($bom->items as $line) {
                $revision->items()->create($line->replicate(['bom_id'])->toArray());
            }

            return $revision;
        });

        return redirect()->route('msfl.boms.edit', $revision)->with('success', 'New version v' . $revision->version . ' created from ' . $bom->bom_no . '.');
    }

    private function nextVersion(int $styleId, ?int $orderId): int
    {
        return (int) Bom::withTrashed()->where('style_id', $styleId)->where('order_id', $orderId)->max('version') + 1;
    }

    private function syncItems(Bom $bom, array $lines): void
    {
        $types = Item::whereIn('id', collect($lines)->pluck('item_id'))->pluck('type', 'id');

        $bom->items()->delete();
        foreach ($lines as $line) {
            $bom->items()->create(array_merge($line, [
                'item_type' => $types[$line['item_id']],
                'wastage_percent' => $line['wastage_percent'] ?? 0,
            ]));
        }
    }

    private function formData(): array
    {
        return [
            'styles' => Lookups::styles(),
            'orders' => Lookups::orders(),
            'itemOptions' => Lookups::items(),
            'colors' => Lookups::colors(),
            'sizes' => Lookups::sizes(),
            'uoms' => Lookups::uoms(),
            'suppliers' => Lookups::suppliers(),
        ];
    }
}
