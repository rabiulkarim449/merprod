<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\InquiryRequest;
use ME\MerchandisingSfl\Models\Inquiry;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Support\Lookups;

class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('msfl_inquiry.list');

        $inquiries = Inquiry::query()
            ->with(['buyer', 'season', 'merchandiser'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('inquiry_no', 'like', '%' . $request->search . '%')
                ->orWhere('style_ref', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $buyers = Lookups::buyers();

        return view('merchandising-sfl::admin.inquiries.index', compact('inquiries', 'buyers'));
    }

    public function create(): View
    {
        $this->authorize('msfl_inquiry.add');

        return view('merchandising-sfl::admin.inquiries.create', $this->formData() + ['inquiry' => null]);
    }

    public function store(InquiryRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $inquiry = DB::transaction(fn () => Inquiry::create($request->validated() + [
            'inquiry_no' => $numbers->next('inquiry', Inquiry::class, 'inquiry_no'),
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('msfl.inquiries.show', $inquiry)->with('success', 'Inquiry ' . $inquiry->inquiry_no . ' created successfully.');
    }

    public function show(Inquiry $inquiry): View
    {
        $this->authorize('msfl_inquiry.view');

        $inquiry->load(['buyer', 'season', 'merchandiser', 'factory', 'productType', 'styles', 'costSheets']);

        return view('merchandising-sfl::admin.inquiries.show', compact('inquiry'));
    }

    public function edit(Inquiry $inquiry): View
    {
        $this->authorize('msfl_inquiry.edit');

        return view('merchandising-sfl::admin.inquiries.edit', $this->formData() + compact('inquiry'));
    }

    public function update(InquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validated();
        $data['lost_reason'] = $data['status'] === 'lost' ? $data['lost_reason'] : null;
        $inquiry->update($data);

        return redirect()->route('msfl.inquiries.show', $inquiry)->with('success', 'Inquiry updated successfully.');
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $this->authorize('msfl_inquiry.delete');

        if ($inquiry->styles()->exists()) {
            return back()->with('error', 'This inquiry already has a tech pack / style — it cannot be deleted.');
        }

        $inquiry->delete();

        return redirect()->route('msfl.inquiries.index')->with('success', 'Inquiry deleted successfully.');
    }

    private function formData(): array
    {
        return [
            'buyers' => Lookups::buyers(),
            'seasons' => Lookups::seasons(),
            'merchandisers' => Lookups::merchandisers(),
            'factories' => Lookups::factories(),
            'productTypes' => Lookups::productTypes(),
        ];
    }
}
