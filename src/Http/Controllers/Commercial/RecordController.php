<?php

namespace ME\MerchandisingSfl\Http\Controllers\Commercial;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Support\CommercialRecordRegistry;

/**
 * List / create / show / edit / delete for every record-keeping screen in CommercialRecordRegistry
 * (Supplier PI, BTB LC, Import Shipment, Export Documents, Bank Realization). The {record}
 * slug comes from the route defaults — each screen has its own route names.
 */
class RecordController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $definition = $this->definition();
        $this->authorize($definition['permission'] . '.list');

        $query = $definition['model']::query()->with($definition['with'] ?? [])->withCount($definition['with_count'] ?? [])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request, $definition) {
                foreach ($definition['search'] ?? [] as $column) {
                    $q->orWhere($column, 'like', '%' . $request->search . '%');
                }
            }))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
        foreach (array_keys($definition['filters'] ?? []) as $filter) {
            $query->when($request->filled($filter), fn ($q) => $q->where($filter, $request->input($filter)));
        }
        $records = $query->latest('id')->paginate($this->perPage(20))->withQueryString();

        foreach ($definition['filters'] ?? [] as $filter => $config) {
            $definition['filters'][$filter]['options'] = $config['options'] instanceof \Closure ? $config['options']() : $config['options'];
        }

        return view('merchandising-sfl::admin.commercial.records.index', compact('definition', 'records'));
    }

    public function create(): View
    {
        $definition = $this->definition();
        $this->authorize($definition['permission'] . '.add');

        return view('merchandising-sfl::admin.commercial.records.form', ['definition' => $definition, 'model' => new $definition['model']()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $definition = $this->definition();
        $record = $definition['slug'];
        $this->authorize($definition['permission'] . '.add');

        $model = new $definition['model']();
        $data = $request->validate(CommercialRecordRegistry::rules($definition, $model));
        $model = DB::transaction(function () use ($definition, $model, $data, $request) {
            $model->fill($this->attributes($definition, $data, $request, null) + ['created_by' => auth()->id()])->save();
            $this->syncPicks($definition, $model, $data);

            return $model;
        });

        return redirect()->route("msfl.commercial.{$record}.show", $model->id)->with('success', $definition['singular'] . ' saved.');
    }

    public function show(): View
    {
        $definition = $this->definition();
        $id = $this->id();
        $this->authorize($definition['permission'] . '.view');
        $model = $definition['model']::query()->with(array_merge($definition['with'] ?? [], ['creator']))->findOrFail($id);

        return view('merchandising-sfl::admin.commercial.records.show', compact('definition', 'model'));
    }

    public function edit(): View
    {
        $definition = $this->definition();
        $id = $this->id();
        $this->authorize($definition['permission'] . '.edit');

        return view('merchandising-sfl::admin.commercial.records.form', ['definition' => $definition, 'model' => $definition['model']::findOrFail($id)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $definition = $this->definition();
        $record = $definition['slug'];
        $id = $this->id();
        $this->authorize($definition['permission'] . '.edit');

        $model = $definition['model']::findOrFail($id);
        $data = $request->validate(CommercialRecordRegistry::rules($definition, $model));
        DB::transaction(function () use ($definition, $model, $data, $request) {
            $model->update($this->attributes($definition, $data, $request, $model->attachment));
            $this->syncPicks($definition, $model, $data);
        });

        return redirect()->route("msfl.commercial.{$record}.show", $model->id)->with('success', $definition['singular'] . ' updated.');
    }

    public function destroy(): RedirectResponse
    {
        $definition = $this->definition();
        $record = $definition['slug'];
        $id = $this->id();
        $this->authorize($definition['permission'] . '.delete');

        $model = $definition['model']::findOrFail($id);
        if (isset($definition['delete_blocked']) && ($reason = ($definition['delete_blocked'])($model))) {
            return back()->with('error', $reason);
        }
        DB::transaction(function () use ($definition, $model) {
            if (isset($definition['before_delete'])) {
                ($definition['before_delete'])($model);
            }
            $model->delete();
        });

        return redirect()->route("msfl.commercial.{$record}.index")->with('success', $definition['singular'] . ' deleted.');
    }

    /** The screen this route belongs to — read by name (route defaults are not passed in URI order). */
    private function definition(): array
    {
        return CommercialRecordRegistry::get((string) request()->route('record'));
    }

    private function id(): int
    {
        return (int) request()->route('id');
    }

    /** Validated data → the model's own columns: picks are synced separately; files are added to / removed from the record's list. */
    private function attributes(array $definition, array $data, Request $request, ?string $currentFile): array
    {
        $attributes = [];
        foreach ($definition['fields'] as $field) {
            if ($field['type'] === 'pick') {
                continue;
            }
            $attributes[$field['name']] = $field['type'] === 'file'
                ? $this->files->storeList((array) $request->file($field['name'], []), 'commercial', $currentFile, (array) ($data['remove_' . $field['name']] ?? []))
                : ($data[$field['name']] ?? null);
        }

        return $attributes;
    }

    private function syncPicks(array $definition, $model, array $data): void
    {
        foreach ($definition['fields'] as $field) {
            if ($field['type'] === 'pick') {
                ($field['sync'])($model, array_map('intval', $data[$field['name']] ?? []));
            }
        }
    }
}
