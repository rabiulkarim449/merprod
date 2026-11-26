<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\SampleQaForm as Form;

/**
 * Sample → Submission Form: the client's "Marketing Sample QA Submission Form",
 * one per sample. The header facts come from the sample (buyer, style, PO,
 * qty, colour / size, type, dates, costing, tech pack); QA types / ticks the rest.
 */
class SampleQaFormController extends Controller
{
    private const PERM = 'msfl_sample_qa_form';

    /** Merchandising → Sample Submission Form: every sample, with whether its form is filled. */
    public function index(Request $request): View
    {
        $this->authorize(self::PERM . '.list');

        $samples = Sample::query()
            ->with(['style', 'buyer', 'sampleType', 'qaForm'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('sample_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('sample_type_id'), fn ($q) => $q->where('sample_type_id', $request->sample_type_id))
            ->when($request->input('form') === 'filled', fn ($q) => $q->has('qaForm'))
            ->when($request->input('form') === 'pending', fn ($q) => $q->doesntHave('qaForm'))
            ->when($request->filled('qa_status'), fn ($q) => $q->whereHas('qaForm', fn ($q) => $q->where('qa_status', $request->qa_status)))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $buyers = \ME\MerchandisingSfl\Support\Lookups::buyers();
        $sampleTypes = \ME\MerchandisingSfl\Support\Lookups::sampleTypes();

        return view('merchandising-sfl::admin.sample-qa-forms.index', compact('samples', 'buyers', 'sampleTypes'));
    }

    public function show(Sample $sample): View
    {
        $this->authorize(self::PERM . '.view');

        return view('merchandising-sfl::admin.sample-qa-forms.show', $this->data($sample));
    }

    public function print(Sample $sample): View
    {
        $this->authorize(self::PERM . '.print');

        return view('merchandising-sfl::admin.sample-qa-forms.print', $this->data($sample));
    }

    public function edit(Sample $sample): View
    {
        $this->authorize(self::PERM . '.edit');

        return view('merchandising-sfl::admin.sample-qa-forms.edit', $this->data($sample));
    }

    public function update(Request $request, Sample $sample): RedirectResponse
    {
        $this->authorize(self::PERM . '.edit');

        $keys = fn (array $list) => Rule::in(array_keys($list));
        $data = $request->validate([
            'brand_market' => ['nullable', 'string', 'max:150'],
            'marketing_purpose' => ['nullable', 'string', 'max:255'],
            'meeting_event' => ['nullable', 'string', 'max:255'],
            'qa_inspection_date' => ['nullable', 'date'],
            'custodian' => ['nullable', 'string', 'max:150'],
            'return_due_date' => ['nullable', 'date'],
            'target_impression' => ['nullable', 'array'],
            'target_impression.*' => [$keys(Form::TARGET_IMPRESSION)],
            'customer_use' => ['nullable', 'array'],
            'customer_use.*' => [$keys(Form::CUSTOMER_USE)],
            'range_coordination' => ['nullable', 'array'],
            'range_coordination.*' => [$keys(Form::RANGE_COORDINATION)],
            'key_selling_story' => ['nullable', 'string', 'max:2000'],
            'qa_status' => ['nullable', $keys(Form::QA_STATUS)],
            'ownership' => ['nullable', $keys(Form::OWNERSHIP)],
            'support_checks' => ['nullable', 'array'],
            'support_checks.*.checked' => ['nullable', 'boolean'],
            'support_checks.*.ref' => ['nullable', 'string', 'max:255'],
            'checkpoints' => ['nullable', 'array'],
            'checkpoints.*.observation' => ['nullable', 'string', 'max:1000'],
            'checkpoints.*.result' => ['nullable', $keys(Form::RESULTS)],
            'checkpoints.*.defect_class' => ['nullable', $keys(Form::DEFECT_CLASSES)],
        ], [], [
            'qa_inspection_date' => 'QA inspection date',
            'return_due_date' => 'Return due date',
        ]);

        // Keep only the known boxes / checkpoints, in the form's order.
        $checks = [];
        foreach (Form::SUPPORT_CHECKS as $k => $label) {
            $c = $data['support_checks'][$k] ?? [];
            $checks[$k] = ['checked' => ! empty($c['checked']), 'ref' => trim((string) ($c['ref'] ?? '')) ?: null];
        }
        $points = [];
        foreach (Form::CHECKPOINTS as $no => $cp) {
            $p = $data['checkpoints'][$no] ?? [];
            $points[$no] = [
                'observation' => trim((string) ($p['observation'] ?? '')) ?: null,
                'result' => $p['result'] ?? null,
                'defect_class' => $p['defect_class'] ?? null,
            ];
        }
        $pick = fn (string $k, array $list) => array_values(array_intersect(array_keys($list), (array) ($data[$k] ?? [])));

        $form = Form::firstOrNew(['sample_id' => $sample->id]);
        $form->fill([
            'brand_market' => $data['brand_market'] ?? null,
            'marketing_purpose' => $data['marketing_purpose'] ?? null,
            'meeting_event' => $data['meeting_event'] ?? null,
            'qa_inspection_date' => $data['qa_inspection_date'] ?? null,
            'custodian' => $data['custodian'] ?? null,
            'return_due_date' => $data['return_due_date'] ?? null,
            'target_impression' => $pick('target_impression', Form::TARGET_IMPRESSION),
            'customer_use' => $pick('customer_use', Form::CUSTOMER_USE),
            'range_coordination' => $pick('range_coordination', Form::RANGE_COORDINATION),
            'key_selling_story' => $data['key_selling_story'] ?? null,
            'qa_status' => $data['qa_status'] ?? null,
            'ownership' => $data['ownership'] ?? null,
            'support_checks' => $checks,
            'checkpoints' => $points,
            'updated_by' => auth()->id(),
        ]);
        if (! $form->exists) {
            $form->created_by = auth()->id();
        }
        $form->save();

        return redirect()->route('msfl.samples.qa-form.show', $sample)->with('success', 'Submission form saved.');
    }

    /** The sample, its saved form (or a blank one) and the facts read from the system. */
    private function data(Sample $sample): array
    {
        $sample->load(['style.productType', 'style.costSheets', 'buyer', 'sampleType', 'order.pos']);
        $style = $sample->style;

        $form = Form::firstOrNew(['sample_id' => $sample->id]);
        if (! $form->exists) {
            $form->brand_market = $sample->buyer->country ?? null;
        }

        // POs of the sample's order for this style (else every PO of the order).
        $pos = $sample->order?->pos ?? collect();
        $stylePos = $pos->where('style_id', $sample->style_id);
        $poNos = ($stylePos->isNotEmpty() ? $stylePos : $pos)->pluck('po_no')->filter()->unique()->implode(', ');

        $costSheet = $style?->costSheets->sortByDesc(fn ($c) => [$c->status === 'approved' ? 1 : 0, $c->id])->first();

        $facts = (object) [
            'customer' => $sample->buyer->name ?? '',
            'style_sample' => trim(($style->style_no ?? '') . ' / ' . $sample->sample_no, ' /'),
            'po_no' => $poNos,
            'qty' => $sample->qty,
            'required_date' => $sample->required_date,
            'costing_ref' => $costSheet->cost_sheet_no ?? '',
            'product_category' => $style?->productType->name ?? '',
            'color_size' => collect([$sample->color_ref, $sample->size_ref])->filter()->implode(' / '),
            'sample_type' => $sample->sampleType->name ?? '',
            'tech_pack' => $style?->tech_pack_file ? 'Tech pack — Style ' . $style->style_no : '',
            'tech_pack_url' => $style?->tech_pack_file ? \ME\MerchandisingSfl\Services\FileUploadService::link($style->tech_pack_file) : null,
        ];

        return compact('sample', 'form', 'facts');
    }
}
