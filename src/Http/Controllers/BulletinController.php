<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\BulletinRequest;
use ME\MerchandisingSfl\Models\Bulletin;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Support\Lookups;

/** Planning → Bulletin: operations of a style, machine types, SMV and the capacity they give. */
class BulletinController extends Controller
{
    private const OPERATION_FIELDS = ['section', 'operation_id', 'name', 'machine_type_id', 'attachment', 'smv', 'workplaces', 'is_active', 'remarks'];

    public function index(Request $request): View
    {
        $this->authorize('msfl_bulletin.list');

        $bulletins = Bulletin::query()
            ->with(['style.buyer', 'line'])
            ->withCount(['operations as active_operations_count' => fn ($q) => $q->where('is_active', true)])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('bulletin_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate($this->perPage(20))
            ->withQueryString();

        return view('merchandising-sfl::admin.bulletins.index', compact('bulletins'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_bulletin.add');

        $bulletin = new Bulletin(['style_id' => $request->integer('style_id') ?: null, 'bulletin_date' => today(), 'working_hours' => 10]);
        $operations = [];

        // "Copy from another bulletin": take its target, hours, line and operations.
        if ($source = Bulletin::with('operations')->find($request->integer('copy_from'))) {
            $bulletin->fill($source->only(['line_id', 'target_per_hour', 'working_hours', 'description']));
            $operations = $source->operations->map(fn ($op) => $op->only(self::OPERATION_FIELDS))->all();
        } elseif ($line = Line::find($request->integer('line_id'))) {
            $bulletin->fill(['line_id' => $line->id, 'working_hours' => round($line->working_minutes / 60, 1)]);
        }
        if (! $bulletin->description && ($style = Style::find($bulletin->style_id))) {
            $bulletin->description = $style->name;
        }

        return view('merchandising-sfl::admin.bulletins.create', $this->formData() + compact('bulletin', 'operations'));
    }

    public function store(BulletinRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = $request->validated();

        $bulletin = DB::transaction(function () use ($data, $numbers) {
            $bulletin = Bulletin::create(Arr::except($data, 'operations') + [
                'bulletin_no' => $numbers->next('bulletin', Bulletin::class, 'bulletin_no'),
                'version' => (int) Bulletin::withTrashed()->where('style_id', $data['style_id'])->max('version') + 1,
                'created_by' => auth()->id(),
            ]);
            $this->syncOperations($bulletin, $data['operations']);

            return $bulletin;
        });

        return redirect()->route('msfl.bulletins.show', $bulletin)->with('success', 'Bulletin ' . $bulletin->bulletin_no . ' created successfully.');
    }

    public function show(Bulletin $bulletin): View
    {
        $this->authorize('msfl_bulletin.view');

        $bulletin->load(['style.buyer', 'line.machines.machineType', 'approver', 'operations.machineType']);
        $machineSummary = $bulletin->machineSummary();

        return view('merchandising-sfl::admin.bulletins.show', compact('bulletin', 'machineSummary'));
    }

    /** The sheet exactly as printed on the floor (no admin layout). */
    public function print(Bulletin $bulletin): View
    {
        $this->authorize('msfl_bulletin.view');

        $bulletin->load(['style.buyer', 'line', 'operations.machineType']);
        $machineSummary = $bulletin->machineSummary();

        return view('merchandising-sfl::admin.bulletins.print', compact('bulletin', 'machineSummary'));
    }

    public function edit(Bulletin $bulletin): View|RedirectResponse
    {
        $this->authorize('msfl_bulletin.edit');

        if (! $bulletin->isEditable()) {
            return redirect()->route('msfl.bulletins.show', $bulletin)->with('error', 'An approved bulletin cannot be edited — use Revise to make a new version');
        }

        $operations = $bulletin->operations->map(fn ($op) => $op->only(self::OPERATION_FIELDS))->all();

        return view('merchandising-sfl::admin.bulletins.edit', $this->formData() + compact('bulletin', 'operations'));
    }

    public function update(BulletinRequest $request, Bulletin $bulletin): RedirectResponse
    {
        abort_unless($bulletin->isEditable(), 403);
        $data = $request->validated();

        DB::transaction(function () use ($bulletin, $data) {
            $bulletin->update(Arr::except($data, 'operations'));
            $this->syncOperations($bulletin, $data['operations']);
        });

        return redirect()->route('msfl.bulletins.show', $bulletin)->with('success', 'Bulletin updated successfully.');
    }

    public function destroy(Bulletin $bulletin): RedirectResponse
    {
        $this->authorize('msfl_bulletin.delete');

        if (! $bulletin->isEditable()) {
            return back()->with('error', 'An approved bulletin cannot be deleted');
        }

        $bulletin->delete();

        return redirect()->route('msfl.bulletins.index')->with('success', 'Bulletin deleted successfully.');
    }

    public function approve(Bulletin $bulletin): RedirectResponse
    {
        $this->authorize('msfl_bulletin.approve');
        abort_unless($bulletin->isEditable(), 403);

        $bulletin->forceFill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();

        return back()->with('success', 'Bulletin approved — new T&As of this style use its SMV.');
    }

    /** Copy an approved bulletin into a new draft version. */
    public function revise(Bulletin $bulletin): RedirectResponse
    {
        $this->authorize('msfl_bulletin.add');

        return redirect()->route('msfl.bulletins.create', ['style_id' => $bulletin->style_id, 'copy_from' => $bulletin->id]);
    }

    private function syncOperations(Bulletin $bulletin, array $operations): void
    {
        $bulletin->operations()->delete();

        foreach (array_values($operations) as $index => $operation) {
            $bulletin->operations()->create(array_merge($operation, [
                'sequence' => ($index + 1) * 10,
                'workplaces' => $operation['workplaces'] ?? null,
                'is_active' => (bool) ($operation['is_active'] ?? false),
            ]));
        }

        $bulletin->recalculate();
    }

    private function formData(): array
    {
        return [
            'styles' => Lookups::styles(),
            'lines' => Lookups::lines(),
            'machineTypes' => Lookups::machineTypes(),
            'operationOptions' => Lookups::operations(),
            'bulletins' => Bulletin::query()->with('style')->latest('id')->limit(200)->get(['id', 'bulletin_no', 'style_id', 'version']),
        ];
    }
}
