<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use ME\MerchandisingSfl\Support\MasterRegistry;

/** Index / create / update / delete for every Master Data screen in MasterRegistry. */
class MasterController extends Controller
{
    public function index(Request $request, string $master): View
    {
        $definition = MasterRegistry::get($master);
        $this->authorize($definition['permission'] . '.list');

        if (isset($definition['before_index'])) {
            ($definition['before_index'])();
        }

        $searchable = $definition['search'] ?? collect($definition['fields'])->whereIn('name', ['code', 'name'])->pluck('name')->all();
        [$orderColumn, $orderDirection] = $definition['order_by'] ?? ['id', 'desc'];

        $query = $definition['model']::query()
            ->with($definition['with'] ?? [])
            ->when($request->filled('search'), function ($query) use ($request, $searchable) {
                $query->where(function ($query) use ($request, $searchable) {
                    foreach ($searchable as $column) {
                        $query->orWhere($column, 'like', '%' . $request->search . '%');
                    }
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'));

        foreach (array_keys($definition['filters'] ?? []) as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }

        $records = $query->orderBy($orderColumn, $orderDirection)->paginate($this->perPage(20))->withQueryString();

        foreach ($definition['filters'] ?? [] as $filter => $options) {
            $definition['filters'][$filter] = $options instanceof \Closure ? $options() : $options;
        }

        // Resolve lazy select options once — every edit modal reuses them.
        foreach ($definition['fields'] as $i => $field) {
            if ($field['type'] === 'select') {
                $definition['fields'][$i]['options'] = MasterRegistry::options($field);
            }
        }

        return view('merchandising-sfl::admin.masters.index', compact('definition', 'records'));
    }

    public function store(Request $request, string $master): RedirectResponse
    {
        $definition = MasterRegistry::get($master);
        $this->authorize($definition['permission'] . '.add');

        $data = $request->validate(MasterRegistry::rules($definition));
        $data['is_active'] = $data['is_active'] ?? true;
        $data['created_by'] = auth()->id();
        $record = $definition['model']::create($data);
        if (method_exists($record, 'submitForApproval')) {
            $record->submitForApproval(); // e.g. a new buyer waits for approval
        }

        return back()->with('success', $definition['singular'] . ' created successfully.');
    }

    public function update(Request $request, string $master, int $id): RedirectResponse
    {
        $definition = MasterRegistry::get($master);
        $this->authorize($definition['permission'] . '.edit');

        $record = $definition['model']::findOrFail($id);
        $data = $request->validate(MasterRegistry::rules($definition, $record->getKey()));
        $data['is_active'] = $data['is_active'] ?? true;
        $record->update($data);
        if (method_exists($record, 'submitForApproval') && $record->approval_status === 'rejected') {
            $record->submitForApproval(); // corrected after a rejection — ask again
        }

        return back()->with('success', $definition['singular'] . ' updated successfully.');
    }

    public function destroy(string $master, int $id): RedirectResponse
    {
        $definition = MasterRegistry::get($master);
        $this->authorize($definition['permission'] . '.delete');

        $record = $definition['model']::findOrFail($id);
        if (isset($definition['delete_blocked']) && ($reason = ($definition['delete_blocked'])($record))) {
            return back()->with('error', $reason);
        }
        $record->delete();

        return back()->with('success', $definition['singular'] . ' deleted successfully.');
    }
}
