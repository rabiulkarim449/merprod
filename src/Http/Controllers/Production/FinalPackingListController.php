<?php

namespace ME\MerchandisingSfl\Http\Controllers\Production;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\FinalPackingList;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Services\PackingFigures;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → Final Packing List: one buyer PO (all its styles) after floor packing —
 * order / cut / ship per size from production, QC's short / excess note per style —
 * then Buyer Approved (date, by, approved copy), which freezes the figures.
 */
class FinalPackingListController extends Controller
{
    public function __construct(private readonly PackingFigures $figures, private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_final_packing_list.list');

        $lists = FinalPackingList::query()->with('order.buyer')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('fpl_no', 'like', '%' . $request->search . '%')->orWhere('po_no', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->whereHas('order', fn ($q) => $q->where('buyer_id', $request->buyer_id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')->paginate($this->perPage(20))->withQueryString();

        return view('merchandising-sfl::admin.production.final-packing-lists.index', ['lists' => $lists, 'buyers' => Lookups::buyers()]);
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_final_packing_list.add');
        $choices = $this->choices();
        $picked = $choices->firstWhere('key', (string) $request->query('po'));
        $fpl = new FinalPackingList(['pack_date' => now()]);
        $sheet = null;
        if ($picked) {
            $fpl->fill(['order_id' => $picked['order_id'], 'po_no' => $picked['po_no']]);
            $sheet = $this->figures->finalSheet($fpl);
        }

        return view('merchandising-sfl::admin.production.final-packing-lists.create', compact('choices', 'picked', 'fpl', 'sheet'));
    }

    public function store(Request $request, DocumentNumberService $numbers): RedirectResponse
    {
        $this->authorize('msfl_final_packing_list.add');
        $data = $this->validated($request);
        $picked = $this->choices()->firstWhere('key', $data['order_id'] . '|' . $data['po_no']);
        if (! $picked) {
            return back()->withInput()->with('error', 'This PO is not a confirmed PO without a final packing list.');
        }

        $fpl = DB::transaction(function () use ($data, $numbers) {
            $fpl = FinalPackingList::create(collect($data)->only(['order_id', 'po_no', 'pack_date', 'invoice_no', 'remarks'])->all() + [
                'fpl_no' => $numbers->next('final_packing_list', FinalPackingList::class, 'fpl_no'), 'created_by' => auth()->id(),
            ]);
            $this->saveNotes($fpl, $data['notes'] ?? []);

            return $fpl;
        });

        return redirect()->route('msfl.production.final-packing-lists.show', $fpl)->with('success', "Final packing list {$fpl->fpl_no} saved — print it for the buyer, then mark it Buyer Approved.");
    }

    public function show(FinalPackingList $finalPackingList): View
    {
        $this->authorize('msfl_final_packing_list.view');

        return view('merchandising-sfl::admin.production.final-packing-lists.show', [
            'fpl' => $finalPackingList->load('order.buyer'), 'sheet' => $this->sheet($finalPackingList),
            'after' => $this->figures->afterPacking($finalPackingList->poLines()->with(['style', 'color', 'order'])->get()),
        ]);
    }

    public function edit(FinalPackingList $finalPackingList): View|RedirectResponse
    {
        $this->authorize('msfl_final_packing_list.edit');
        if (! $finalPackingList->isEditable()) {
            return redirect()->route('msfl.production.final-packing-lists.show', $finalPackingList)->with('error', 'A buyer-approved packing list cannot be edited — re-open it first.');
        }

        return view('merchandising-sfl::admin.production.final-packing-lists.edit', ['fpl' => $finalPackingList, 'sheet' => $this->sheet($finalPackingList)]);
    }

    public function update(Request $request, FinalPackingList $finalPackingList): RedirectResponse
    {
        $this->authorize('msfl_final_packing_list.edit');
        abort_unless($finalPackingList->isEditable(), 403);
        $data = $this->validated($request, $finalPackingList);

        DB::transaction(function () use ($finalPackingList, $data) {
            $finalPackingList->update(collect($data)->only(['pack_date', 'invoice_no', 'remarks'])->all());
            $this->saveNotes($finalPackingList, $data['notes'] ?? []);
        });

        return redirect()->route('msfl.production.final-packing-lists.show', $finalPackingList)->with('success', 'Final packing list updated.');
    }

    /** Buyer approved: date, who, the approved copy — the figures are frozen from now on. */
    public function approve(Request $request, FinalPackingList $finalPackingList): RedirectResponse
    {
        $this->authorize('msfl_final_packing_list.approve');
        abort_unless($finalPackingList->isEditable(), 403);
        $data = $request->validate([
            'buyer_approved_on' => ['required', 'date'],
            'buyer_approved_by' => ['nullable', 'string', 'max:255'],
            'approval_file' => ['nullable', ...FileUploadService::RULES],
        ]);

        $finalPackingList->forceFill([
            'status' => 'approved',
            'buyer_approved_on' => $data['buyer_approved_on'],
            'buyer_approved_by' => $data['buyer_approved_by'] ?? null,
            'approval_file' => $this->files->store($request->file('approval_file'), 'packing-lists', $finalPackingList->approval_file),
            'snapshot' => $this->sheet($finalPackingList),
        ])->save();

        return back()->with('success', 'Marked Buyer Approved — the figures are frozen on this packing list.');
    }

    /** Back to draft (figures live again). */
    public function reopen(FinalPackingList $finalPackingList): RedirectResponse
    {
        $this->authorize('msfl_final_packing_list.approve');
        $finalPackingList->forceFill(['status' => 'draft', 'snapshot' => null])->save();

        return back()->with('success', 'Re-opened — figures are live from production again.');
    }

    public function destroy(FinalPackingList $finalPackingList): RedirectResponse
    {
        $this->authorize('msfl_final_packing_list.delete');
        if (! $finalPackingList->isEditable()) {
            return back()->with('error', 'A buyer-approved packing list cannot be deleted — re-open it first.');
        }
        $finalPackingList->delete();

        return redirect()->route('msfl.production.final-packing-lists.index')->with('success', 'Final packing list deleted.');
    }

    /** Frozen figures once approved, otherwise live from production. */
    private function sheet(FinalPackingList $fpl): array
    {
        if ($fpl->status === 'approved' && $fpl->snapshot) {
            // Figures stay as approved; invoices are made later, so their numbers stay live.
            return ['invoices' => $this->figures->finalSheet($fpl)['invoices']] + $fpl->snapshot;
        }

        return $this->figures->finalSheet($fpl, $fpl->notes()->pluck('note', 'order_po_id')->map(fn ($n) => (string) $n)->all());
    }

    private function saveNotes(FinalPackingList $fpl, array $notes): void
    {
        $poIds = $fpl->poLines()->pluck('id');
        foreach ($notes as $poId => $note) {
            if ($poIds->contains((int) $poId)) {
                $fpl->notes()->updateOrCreate(['order_po_id' => (int) $poId], ['note' => $note]);
            }
        }
    }

    private function validated(Request $request, ?FinalPackingList $fpl = null): array
    {
        return $request->validate(($fpl ? [] : [
            'order_id' => ['required', 'integer'],
            'po_no' => ['required', 'string', 'max:100'],
        ]) + [
            'pack_date' => ['required', 'date'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** Confirmed buyer POs (order + PO no, all its styles) without a final packing list yet. */
    private function choices(): Collection
    {
        $taken = FinalPackingList::query()->get(['order_id', 'po_no'])->map(fn ($f) => $f->order_id . '|' . $f->po_no)->all();

        return OrderPo::query()->with(['order.buyer', 'style'])->whereHas('order', fn ($q) => $q->where('status', 'confirmed'))
            ->orderByDesc('order_id')->orderBy('po_no')->get()
            ->groupBy(fn ($p) => $p->order_id . '|' . $p->po_no)
            ->reject(fn ($g, $key) => in_array($key, $taken, true))
            ->map(fn ($g, $key) => [
                'key' => $key, 'order_id' => $g->first()->order_id, 'po_no' => $g->first()->po_no,
                'label' => 'PO ' . $g->first()->po_no . ' — ' . ($g->first()->order->buyer->name ?? '') . ' (' . $g->pluck('style.style_no')->filter()->unique()->implode(', ') . ') · ' . ($g->first()->order->order_no ?? ''),
            ])->values();
    }
}
