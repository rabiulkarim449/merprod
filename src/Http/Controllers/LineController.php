<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\FloorLine;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Services\InventoryMachines;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Planning → Setup → Lines: planning figures (headcount, working day,
 * efficiency) for each HR floor-line. Floor / line names come from HR,
 * machines from Inventory.
 */
class LineController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('msfl_line.list');

        $lines = Line::query()
            ->with(['floorLine', 'machines.machineType'])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('floorLine', fn ($q) => $q
                ->where('line_name', 'like', '%' . $request->search . '%')
                ->orWhere('floor_name', 'like', '%' . $request->search . '%')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy(FloorLine::select('floor_name')->whereColumn('hr_floor_lines.id', 'msfl_lines.hr_floor_line_id'))
            ->orderBy(FloorLine::select('line_name')->whereColumn('hr_floor_lines.id', 'msfl_lines.hr_floor_line_id'))
            ->paginate($this->perPage(20))
            ->withQueryString();

        // HR floor-lines: the ones not set up yet can be added; a line's own one stays selectable on edit.
        $floorLines = FloorLine::query()->active()->orderBy('floor_name')->orderBy('line_name')->get();
        $usedFloorLineIds = Line::withTrashed()->pluck('hr_floor_line_id')->all();

        $machineTypes = Lookups::machineTypes();
        $fromInventory = InventoryMachines::available();
        $unmatchedLines = InventoryMachines::unmatchedLines();

        return view('merchandising-sfl::admin.lines.index', compact('lines', 'machineTypes', 'fromInventory', 'unmatchedLines', 'floorLines', 'usedFloorLineIds'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('msfl_line.add');
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $line = Line::create($data['line'] + ['created_by' => auth()->id()]);
            $this->syncMachines($line, $data['machines']);
        });

        return back()->with('success', 'Line created successfully.');
    }

    public function update(Request $request, Line $line): RedirectResponse
    {
        $this->authorize('msfl_line.edit');
        $data = $this->validated($request, $line);

        DB::transaction(function () use ($line, $data) {
            $line->update($data['line']);
            $this->syncMachines($line, $data['machines']);
        });

        return back()->with('success', 'Line updated successfully.');
    }

    public function destroy(Line $line): RedirectResponse
    {
        $this->authorize('msfl_line.delete');

        if ($line->tnaPlans()->where('status', 'active')->exists()) {
            return back()->with('error', 'This line is loaded in an active T&A — it cannot be deleted');
        }

        $line->delete();

        return back()->with('success', 'Line deleted successfully.');
    }

    private function validated(Request $request, ?Line $line = null): array
    {
        $data = $request->validate([
            'hr_floor_line_id' => [
                'required', Rule::exists('hr_floor_lines', 'id'),
                Rule::unique('msfl_lines', 'hr_floor_line_id')->ignore($line),
            ],
            'operators' => ['required', 'integer', 'min:0'],
            'helpers' => ['nullable', 'integer', 'min:0'],
            'working_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'efficiency_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'machines' => ['nullable', 'array'],
            'machines.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $machines = $data['machines'] ?? [];
        unset($data['machines']);
        $data['helpers'] = $data['helpers'] ?? 0;
        $data['is_active'] = $data['is_active'] ?? true;

        return ['line' => $data, 'machines' => $machines];
    }

    /**
     * Hand-typed machine quantities — only when Inventory isn't installed;
     * otherwise the line's machines are counted from Inventory.
     *
     * @param array<int|string, int|string|null> $machines machine_type_id => qty
     */
    private function syncMachines(Line $line, array $machines): void
    {
        if (InventoryMachines::available()) {
            return;
        }

        $line->machines()->delete();

        foreach ($machines as $typeId => $qty) {
            if ((int) $qty > 0) {
                $line->machines()->create(['machine_type_id' => $typeId, 'qty' => (int) $qty]);
            }
        }
    }
}
