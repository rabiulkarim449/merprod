<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\CostSheetRequest;
use ME\MerchandisingSfl\Models\CostSheet;
use ME\MerchandisingSfl\Models\Inquiry;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Support\Lookups;

/** Dev (R&D) — pre-order costing. */
class CostSheetController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('msfl_cost_sheet.list');

        $costSheets = CostSheet::query()
            ->with(['buyer', 'style', 'currency'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('cost_sheet_no', 'like', '%' . $request->search . '%')
                ->orWhere('style_ref', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $buyers = Lookups::buyers();

        return view('merchandising-sfl::admin.cost-sheets.index', compact('costSheets', 'buyers'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_cost_sheet.add');

        $costSheet = new CostSheet(['costing_date' => now(), 'price_type' => 'FOB', 'efficiency_percent' => 100, 'cm_cost' => 0, 'commercial_percent' => 0, 'other_cost' => 0, 'profit_percent' => 0]);

        if ($style = Style::find($request->integer('style_id'))) {
            $costSheet->fill([
                'style_id' => $style->id, 'inquiry_id' => $style->inquiry_id, 'buyer_id' => $style->buyer_id,
                'style_ref' => $style->style_no, 'garment_description' => $style->name, 'smv' => $style->smv,
                'cm_cost' => $style->confirm_cm ?? $style->target_cm ?? 0,
            ]);
        } elseif ($inquiry = Inquiry::find($request->integer('inquiry_id'))) {
            $costSheet->fill([
                'inquiry_id' => $inquiry->id, 'buyer_id' => $inquiry->buyer_id, 'style_ref' => $inquiry->style_ref,
                'order_qty' => $inquiry->order_qty, 'buyer_target_price' => $inquiry->unit_price,
            ]);
        }

        return view('merchandising-sfl::admin.cost-sheets.create', $this->formData() + compact('costSheet'));
    }

    public function store(CostSheetRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = $this->withCm($request->validated());

        $costSheet = DB::transaction(function () use ($data, $numbers) {
            $costSheet = CostSheet::create(Arr::except($data, 'items') + [
                'cost_sheet_no' => $numbers->next('cost_sheet', CostSheet::class, 'cost_sheet_no'),
                'created_by' => auth()->id(),
            ]);
            $costSheet->items()->createMany($data['items']);
            $costSheet->recalculate();

            return $costSheet;
        });

        return redirect()->route('msfl.cost-sheets.show', $costSheet)->with('success', 'Cost sheet ' . $costSheet->cost_sheet_no . ' created successfully.');
    }

    public function show(CostSheet $costSheet): View
    {
        $this->authorize('msfl_cost_sheet.view');

        $costSheet->load(['buyer', 'style.images', 'inquiry', 'currency', 'approver', 'items.item', 'items.uom']);

        return view('merchandising-sfl::admin.cost-sheets.show', compact('costSheet'));
    }

    public function edit(CostSheet $costSheet): View|RedirectResponse
    {
        $this->authorize('msfl_cost_sheet.edit');

        if (! $costSheet->isEditable()) {
            return redirect()->route('msfl.cost-sheets.show', $costSheet)->with('error', 'An approved cost sheet cannot be edited');
        }

        $costSheet->load('items');

        return view('merchandising-sfl::admin.cost-sheets.edit', $this->formData() + compact('costSheet'));
    }

    public function update(CostSheetRequest $request, CostSheet $costSheet): RedirectResponse
    {
        abort_unless($costSheet->isEditable(), 403, 'An approved cost sheet cannot be edited.');
        $data = $this->withCm($request->validated());

        DB::transaction(function () use ($data, $costSheet) {
            $costSheet->update(Arr::except($data, 'items'));
            $costSheet->items()->delete();
            $costSheet->items()->createMany($data['items']);
            $costSheet->unsetRelation('items')->recalculate();
        });

        return redirect()->route('msfl.cost-sheets.show', $costSheet)->with('success', 'Cost sheet updated successfully.');
    }

    public function destroy(CostSheet $costSheet): RedirectResponse
    {
        $this->authorize('msfl_cost_sheet.delete');

        if (! $costSheet->isEditable()) {
            return back()->with('error', 'An approved cost sheet cannot be deleted');
        }

        $costSheet->delete();

        return redirect()->route('msfl.cost-sheets.index')->with('success', 'Cost sheet deleted successfully.');
    }

    public function approve(CostSheet $costSheet): RedirectResponse
    {
        $this->authorize('msfl_cost_sheet.approve');
        abort_unless($costSheet->isEditable(), 403);

        $costSheet->forceFill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();

        return back()->with('success', 'Cost sheet approved.');
    }

    /** No CM typed: take it from SMV × cost per minute ÷ efficiency. */
    private function withCm(array $data): array
    {
        if (! (float) ($data['cm_cost'] ?? 0)) {
            $data['cm_cost'] = (new CostSheet($data))->cmFromMinutes() ?? 0;
        }

        return $data;
    }

    private function formData(): array
    {
        return [
            'suppliers' => Lookups::suppliers()->pluck('name', 'id'),
            'buyers' => Lookups::buyers(),
            'styles' => Lookups::styles(),
            'inquiries' => Lookups::inquiries(),
            'currencies' => Lookups::currencies(),
            'itemOptions' => Lookups::items(),
            'uoms' => Lookups::uoms(),
        ];
    }
}
