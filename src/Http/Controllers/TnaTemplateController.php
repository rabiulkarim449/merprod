<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\TnaPlan;
use ME\MerchandisingSfl\Models\TnaTemplate;
use ME\MerchandisingSfl\Models\TnaTemplateTask;

/** Planning → Setup → T&A Templates: the steps of a T&A and when each falls. */
class TnaTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('msfl_tna_template.list');

        $templates = TnaTemplate::query()->withCount('tasks')->orderByDesc('is_default')->orderBy('name')->get();

        return view('merchandising-sfl::admin.tna-templates.index', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('msfl_tna_template.add');
        $data = $this->validateHeader($request);

        $template = DB::transaction(function () use ($data, $request) {
            $template = TnaTemplate::create($data + ['created_by' => auth()->id()]);

            // Start from a copy of another template's steps, if chosen.
            if ($source = TnaTemplate::find($request->integer('copy_from'))) {
                foreach ($source->tasks as $task) {
                    $template->tasks()->create($task->replicate(['tna_template_id'])->toArray());
                }
            }
            $this->keepSingleDefault($template);

            return $template;
        });

        return redirect()->route('msfl.tna-templates.edit', $template)->with('success', 'Template created — now set its steps.');
    }

    public function edit(TnaTemplate $tnaTemplate): View
    {
        $this->authorize('msfl_tna_template.edit');

        $tnaTemplate->load('tasks');
        $autoSources = TnaTemplateTask::autoSources();

        return view('merchandising-sfl::admin.tna-templates.edit', ['template' => $tnaTemplate, 'autoSources' => $autoSources]);
    }

    public function update(Request $request, TnaTemplate $tnaTemplate): RedirectResponse
    {
        $this->authorize('msfl_tna_template.edit');
        $header = $this->validateHeader($request);

        $request->merge(['tasks' => array_values(array_filter($request->input('tasks', []), fn ($t) => filled($t['task_name'] ?? null)))]);
        $tasks = $request->validate([
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.group_name' => ['required', 'string', 'max:50'],
            'tasks.*.task_code' => ['required', 'alpha_dash', 'max:50', 'distinct'],
            'tasks.*.task_name' => ['required', 'string', 'max:255'],
            'tasks.*.anchor' => ['required', Rule::in(array_keys(TnaTemplateTask::ANCHORS))],
            'tasks.*.offset_days' => ['required', 'integer', 'min:-365', 'max:365'],
            'tasks.*.auto_source' => ['required', Rule::in(array_keys(TnaTemplateTask::autoSources()))],
            'tasks.*.condition' => ['nullable', Rule::in(array_keys(TnaTemplateTask::CONDITIONS))],
            'tasks.*.is_mandatory' => ['nullable', 'boolean'],
        ], ['tasks.*.task_code.distinct' => 'Each step needs its own code.'])['tasks'];

        DB::transaction(function () use ($tnaTemplate, $header, $tasks) {
            $tnaTemplate->update($header);
            $this->keepSingleDefault($tnaTemplate);

            // Existing T&As keep their own snapshot of the steps — replacing these is safe.
            $tnaTemplate->tasks()->delete();
            foreach ($tasks as $index => $task) {
                $tnaTemplate->tasks()->create($task + ['sequence' => ($index + 1) * 10, 'is_mandatory' => (bool) ($task['is_mandatory'] ?? false)]);
            }
        });

        return back()->with('success', 'Template saved. New T&As use it; existing T&As are unchanged.');
    }

    public function destroy(TnaTemplate $tnaTemplate): RedirectResponse
    {
        $this->authorize('msfl_tna_template.delete');

        if (TnaPlan::where('tna_template_id', $tnaTemplate->id)->exists()) {
            return back()->with('error', 'This template is used by a T&A — deactivate it instead');
        }

        $tnaTemplate->delete();

        return redirect()->route('msfl.tna-templates.index')->with('success', 'Template deleted.');
    }

    private function validateHeader(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'ship_to_ex_factory_days' => ['required', 'integer', 'min:0', 'max:60'],
            'ex_factory_to_sewing_end_days' => ['required', 'integer', 'min:0', 'max:60'],
            'pcd_to_sewing_start_days' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        return $data + ['is_default' => false, 'is_active' => true];
    }

    private function keepSingleDefault(TnaTemplate $template): void
    {
        if ($template->is_default) {
            TnaTemplate::whereKeyNot($template->id)->update(['is_default' => false]);
        }
    }
}
