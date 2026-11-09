<?php

namespace ME\MerchandisingSfl\Http\Controllers\Commercial;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Http\Requests\Commercial\ExportLcRequest;
use ME\MerchandisingSfl\Models\Commercial\Bank;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Models\Commercial\PaymentTerm;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Services\Commercial\LcStatus;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Commercial → Export LC / Sales Contract: header and its POs on one page.
 * Changing value / last shipment / expiry of an active LC is logged as an amendment.
 */
class ExportLcController extends Controller
{
    public function __construct(private readonly LcStatus $status, private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_export_lc.list');

        $lcs = ExportLc::query()->with(['buyer', 'currency'])->withCount('pos')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('lc_no', 'like', '%' . $request->search . '%')
                ->orWhere('buyer_lc_no', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')->paginate($this->perPage(20))->withQueryString();
        $figures = $lcs->getCollection()->mapWithKeys(fn ($lc) => [$lc->id => $this->status->figures($lc)]);

        return view('merchandising-sfl::admin.commercial.export-lcs.index', ['lcs' => $lcs, 'figures' => $figures, 'buyers' => Lookups::buyers()]);
    }

    public function create(): View
    {
        $this->authorize('msfl_export_lc.add');

        return view('merchandising-sfl::admin.commercial.export-lcs.create', $this->formData(new ExportLc([
            'type' => 'lc', 'lc_date' => now(), 'tolerance_percent' => 0,
            'payment_term_id' => PaymentTerm::query()->active()->where('term_type', 'sight')->orderBy('id')->value('id'),
        ])));
    }

    public function store(ExportLcRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['attachment', 'remove_attachment', 'po_ids', 'amendment_remarks']);
        $data['attachment'] = $this->files->storeList((array) $request->file('attachment', []), 'commercial', null);

        $lc = DB::transaction(function () use ($data, $numbers, $request) {
            $lc = ExportLc::create($data + ['lc_no' => $numbers->next('export_lc', ExportLc::class, 'lc_no'), 'created_by' => auth()->id()]);
            $lc->pos()->sync($request->input('po_ids', []));

            return $lc;
        });

        return redirect()->route('msfl.commercial.export-lcs.show', $lc)->with('success', "Export LC {$lc->lc_no} created with " . $lc->pos()->count() . ' PO(s). Activate it to make invoices.');
    }

    public function show(ExportLc $exportLc): View
    {
        $this->authorize('msfl_export_lc.view');
        $exportLc->load(['buyer', 'currency', 'paymentTerm', 'issuingBank', 'lienBank', 'creator', 'amendments.changer', 'invoices.shipMode']);

        return view('merchandising-sfl::admin.commercial.export-lcs.show', [
            'lc' => $exportLc, 'figures' => $this->status->figures($exportLc), 'rows' => $this->status->poRows($exportLc),
        ]);
    }

    public function edit(ExportLc $exportLc): View|RedirectResponse
    {
        $this->authorize('msfl_export_lc.edit');
        if (! $exportLc->isEditable()) {
            return redirect()->route('msfl.commercial.export-lcs.show', $exportLc)->with('error', 'A closed LC cannot be edited — re-open it first.');
        }

        return view('merchandising-sfl::admin.commercial.export-lcs.edit', $this->formData($exportLc->load('pos')));
    }

    public function update(ExportLcRequest $request, ExportLc $exportLc): RedirectResponse
    {
        abort_unless($exportLc->isEditable(), 403);
        $data = Arr::except($request->validated(), ['attachment', 'remove_attachment', 'po_ids', 'amendment_remarks']);
        $data['attachment'] = $this->files->storeList((array) $request->file('attachment', []), 'commercial', $exportLc->attachment, (array) $request->input('remove_attachment', []));

        DB::transaction(function () use ($exportLc, $data, $request) {
            $before = $this->amendable($exportLc);
            $exportLc->update($data);
            $exportLc->pos()->sync($request->input('po_ids', []));

            // After activation a change of value / dates is a buyer amendment — keep the history.
            if ($exportLc->status === 'active') {
                $next = (int) $exportLc->amendments()->max('amendment_no') + 1;
                foreach ($this->amendable($exportLc->refresh()) as $field => $value) {
                    if ($value !== $before[$field]) {
                        $exportLc->amendments()->create([
                            'amendment_no' => $next, 'amendment_date' => today(), 'field' => $field, 'old_value' => $before[$field],
                            'new_value' => $value, 'remarks' => $request->input('amendment_remarks'), 'changed_by' => auth()->id(),
                        ]);
                    }
                }
            }
        });

        return redirect()->route('msfl.commercial.export-lcs.show', $exportLc)->with('success', 'Export LC updated.');
    }

    public function destroy(ExportLc $exportLc): RedirectResponse
    {
        $this->authorize('msfl_export_lc.delete');
        if ($exportLc->status !== 'draft' || $exportLc->invoices()->exists()) {
            return back()->with('error', 'Only a draft LC without invoices can be deleted — close it instead.');
        }
        DB::transaction(function () use ($exportLc) {
            $exportLc->pos()->detach();
            $exportLc->delete();
        });

        return redirect()->route('msfl.commercial.export-lcs.index')->with('success', 'Export LC deleted.');
    }

    /** Activate (draft → active), Close (active → closed), Re-open (closed → active). */
    public function changeStatus(Request $request, ExportLc $exportLc): RedirectResponse
    {
        $this->authorize('msfl_export_lc.approve');
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'closed'])]])['status'];

        if ($status === 'active' && ! $exportLc->pos()->exists()) {
            return back()->with('error', 'Add at least one PO before activating the LC.');
        }
        if ($status === 'closed' && $exportLc->status !== 'active') {
            return back()->with('error', 'Only an active LC can be closed.');
        }
        $exportLc->forceFill(['status' => $status])->save();

        return back()->with('success', 'Export LC ' . strtolower(ExportLc::STATUSES[$status][0]) . '.');
    }

    private function amendable(ExportLc $lc): array
    {
        return [
            'lc_value' => number_format((float) $lc->lc_value, 2, '.', ''),
            'last_shipment_date' => $lc->last_shipment_date?->toDateString(),
            'expiry_date' => $lc->expiry_date?->toDateString(),
        ];
    }

    private function formData(ExportLc $lc): array
    {
        // Every buyer's selectable POs; the form shows the picked buyer's.
        $pos = Lookups::buyers()->flatMap(fn ($b) => $this->status->selectablePos($b->id, $lc));

        return [
            'lc' => $lc,
            'buyers' => Lookups::buyers(),
            'currencies' => Lookups::currencies(),
            'paymentTerms' => PaymentTerm::query()->active()->orderBy('days')->orderBy('name')->get(),
            'banks' => Bank::query()->active()->orderBy('name')->get(),
            'pos' => $pos,
            'pickedPoIds' => collect(old('po_ids', $lc->exists ? $lc->pos->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all(),
        ];
    }
}
