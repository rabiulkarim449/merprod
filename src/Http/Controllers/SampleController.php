<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\SampleRequest;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Sample Stages — requested → in_progress → submitted → approved / rejected.
 * A rejection can raise the next revision (a new requested row linked to it).
 */
class SampleController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_sample.list');

        $samples = Sample::query()
            ->with(['style', 'buyer', 'sampleType', 'merchandiser'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('sample_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('sample_type_id'), fn ($q) => $q->where('sample_type_id', $request->sample_type_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereIn('status', ['requested', 'in_progress'])->whereDate('required_date', '<', today()))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $buyers = Lookups::buyers();
        $sampleTypes = Lookups::sampleTypes();

        return view('merchandising-sfl::admin.samples.index', compact('samples', 'buyers', 'sampleTypes'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_sample.add');

        $sample = new Sample(['request_date' => now(), 'qty' => 1, 'merchandiser_id' => auth()->id()]);
        if ($style = Style::find($request->integer('style_id'))) {
            $sample->fill(['style_id' => $style->id, 'merchandiser_id' => $style->merchandiser_id ?? auth()->id()]);
        }

        return view('merchandising-sfl::admin.samples.create', $this->formData() + compact('sample'));
    }

    public function store(SampleRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = Arr::except($request->validated(), 'attachment');
        $data['attachment'] = $this->files->store($request->file('attachment'), 'samples');

        $sample = DB::transaction(function () use ($data, $numbers) {
            $style = Style::findOrFail($data['style_id']);
            $sample = Sample::create($data + [
                'sample_no' => $numbers->next('sample', Sample::class, 'sample_no'),
                'buyer_id' => $style->buyer_id,
                'created_by' => auth()->id(),
            ]);
            $this->markStyleInSampleStage($style);

            return $sample;
        });

        return redirect()->route('msfl.samples.show', $sample)->with('success', 'Sample ' . $sample->sample_no . ' requested.');
    }

    public function show(Sample $sample): View
    {
        $this->authorize('msfl_sample.view');

        $sample->load(['style', 'buyer', 'sampleType', 'order', 'merchandiser', 'decider', 'parent', 'revisions', 'comments.author']);

        return view('merchandising-sfl::admin.samples.show', compact('sample'));
    }

    public function edit(Sample $sample): View|RedirectResponse
    {
        $this->authorize('msfl_sample.edit');

        if (! $sample->isEditable()) {
            return redirect()->route('msfl.samples.show', $sample)->with('error', 'A ' . $sample->statusLabel() . ' sample cannot be edited');
        }

        return view('merchandising-sfl::admin.samples.edit', $this->formData() + compact('sample'));
    }

    public function update(SampleRequest $request, Sample $sample): RedirectResponse
    {
        abort_unless($sample->isEditable(), 403);

        $data = Arr::except($request->validated(), 'attachment');
        $data['attachment'] = $this->files->store($request->file('attachment'), 'samples', $sample->attachment);
        $data['buyer_id'] = Style::whereKey($data['style_id'])->value('buyer_id');
        $sample->update($data);

        return redirect()->route('msfl.samples.show', $sample)->with('success', 'Sample updated successfully.');
    }

    public function destroy(Sample $sample): RedirectResponse
    {
        $this->authorize('msfl_sample.delete');

        if ($sample->status !== 'requested') {
            return back()->with('error', 'Only a requested sample can be deleted — cancel it instead');
        }

        $sample->delete();

        return redirect()->route('msfl.samples.index')->with('success', 'Sample deleted successfully.');
    }

    /** requested → in_progress, requested / in_progress → cancelled. */
    public function changeStatus(Request $request, Sample $sample): RedirectResponse
    {
        $this->authorize('msfl_sample.edit');
        $status = $request->validate(['status' => ['required', 'in:in_progress,cancelled']])['status'];

        $allowed = ['in_progress' => ['requested'], 'cancelled' => ['requested', 'in_progress']];
        if (! in_array($sample->status, $allowed[$status], true)) {
            return back()->with('error', 'A ' . $sample->statusLabel() . ' sample cannot be moved to ' . Sample::STATUSES[$status][0]);
        }

        $sample->forceFill(['status' => $status])->save();

        return back()->with('success', 'Sample marked ' . Sample::STATUSES[$status][0] . '.');
    }

    /** Sent to the buyer. */
    public function submit(Request $request, Sample $sample): RedirectResponse
    {
        $this->authorize('msfl_sample.edit');
        abort_unless($sample->isEditable(), 403);

        $data = $request->validate([
            'submit_date' => ['required', 'date', 'after_or_equal:' . $sample->request_date->format('Y-m-d')],
            'courier_name' => ['nullable', 'string', 'max:100'],
            'tracking_no' => ['nullable', 'string', 'max:100'],
        ]);

        $sample->forceFill($data + ['status' => 'submitted'])->save();
        app(\ME\MerchandisingSfl\Services\SampleDecision::class)->requestApproval($sample);

        return back()->with('success', 'Sample marked as submitted to buyer.');
    }

    /** Buyer decision on a submitted sample. */
    public function decide(Request $request, Sample $sample): RedirectResponse
    {
        $this->authorize('msfl_sample.approve');
        abort_unless($sample->status === 'submitted', 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_date' => ['required', 'date'],
            'buyer_comments' => ['nullable', 'required_if:decision,rejected', 'string', 'max:5000'],
            'create_revision' => ['nullable', 'boolean'],
        ]);

        $decisions = app(\ME\MerchandisingSfl\Services\SampleDecision::class);
        $revision = $decisions->decide($sample, $data['decision'], $data['decision_date'], $data['buyer_comments'] ?? null, ! empty($data['create_revision']), auth()->id());
        $decisions->closeCentral($sample, $data['decision'], $data['buyer_comments'] ?? null, auth()->id());

        if ($revision) {
            return redirect()->route('msfl.samples.show', $revision)
                ->with('success', $sample->sample_no . ' rejected — revision ' . $revision->revision_no . ' (' . $revision->sample_no . ') requested.');
        }

        return back()->with('success', 'Sample ' . $data['decision'] . '.');
    }

    public function storeComment(Request $request, Sample $sample): RedirectResponse
    {
        $this->authorize('msfl_sample.edit');

        $data = $request->validate([
            'comment_date' => ['required', 'date'],
            'comment' => ['required', 'string', 'max:5000'],
            'is_buyer_comment' => ['nullable', 'boolean'],
            'attachment' => ['nullable', ...FileUploadService::RULES],
        ]);

        $sample->comments()->create([
            'comment_date' => $data['comment_date'],
            'comment' => $data['comment'],
            'is_buyer_comment' => $data['is_buyer_comment'] ?? false,
            'attachment' => $this->files->store($request->file('attachment'), 'samples/comments'),
            'commented_by' => auth()->id(),
        ]);

        return back()->with('success', 'Comment added.');
    }

    /** First sample of a style moves its development status forward. */
    private function markStyleInSampleStage(Style $style): void
    {
        if (in_array($style->development_status, ['new', 'in_development'], true)) {
            $style->update(['development_status' => 'sample_stage']);
        }
    }

    private function formData(): array
    {
        return [
            'styles' => Lookups::styles(),
            'sampleTypes' => Lookups::sampleTypes(),
            'orders' => Lookups::orders(),
            'merchandisers' => Lookups::merchandisers(),
        ];
    }
}
