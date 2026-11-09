<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Models\TnaPlan;
use ME\MerchandisingSfl\Models\TnaTemplate;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\TnaPlanner;
use ME\MerchandisingSfl\Support\Lookups;

/** Planning → T&A: one plan per confirmed order + style, dates worked out by TnaPlanner. */
class TnaPlanController extends Controller
{
    public function __construct(private readonly TnaPlanner $planner)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_tna.list');

        $plans = TnaPlan::query()
            ->with(['order.buyer', 'style', 'lines', 'tasks'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('tna_no', 'like', '%' . $request->search . '%')
                ->orWhereHas('style', fn ($q) => $q->where('style_no', 'like', '%' . $request->search . '%'))
                ->orWhereHas('order', fn ($q) => $q->where('order_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('line_id'), fn ($q) => $q->whereHas('lines', fn ($q) => $q->where('msfl_lines.id', $request->line_id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("status = 'active' desc")
            ->orderBy('sewing_start_date')
            ->paginate($this->perPage(20))
            ->withQueryString();

        $lines = Lookups::lines();

        return view('merchandising-sfl::admin.tna.index', compact('plans', 'lines'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_tna.add');

        $orders = Order::query()->with('buyer')->where('status', 'confirmed')->latest('id')->get();
        $order = $orders->firstWhere('id', $request->integer('order_id'));

        // Each style of the chosen order, with what the T&A will start from.
        $styleRows = collect();
        if ($order) {
            $taken = TnaPlan::where('order_id', $order->id)->where('status', '!=', 'cancelled')->pluck('style_id')->all();
            $styleRows = Style::whereIn('id', $order->pos()->select('style_id'))->get()
                ->map(fn (Style $style) => ['style' => $style, 'taken' => in_array($style->id, $taken, true)] + $this->planner->inputsFromOrder($order, $style));
        }

        $lines = Lookups::lines();
        $templates = TnaTemplate::query()->active()->orderByDesc('is_default')->orderBy('name')->get();

        return view('merchandising-sfl::admin.tna.create', compact('orders', 'order', 'styleRows', 'lines', 'templates'));
    }

    public function store(Request $request, DocumentNumberService $numbers): RedirectResponse
    {
        $this->authorize('msfl_tna.add');

        $data = $request->validate([
            'order_id' => ['required', Rule::exists('msfl_orders', 'id')->where('status', 'confirmed')],
            'style_id' => ['required', Rule::exists('msfl_styles', 'id')],
            'tna_template_id' => ['required', Rule::exists('msfl_tna_templates', 'id')->whereNull('deleted_at')],
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => [Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
            'shipment_date' => ['nullable', 'date'],
            'smv' => ['nullable', 'numeric', 'gt:0'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], ['order_id.exists' => 'A T&A can only be made for a confirmed order.']);

        $order = Order::findOrFail($data['order_id']);
        $style = Style::findOrFail($data['style_id']);
        $inputs = $this->planner->inputsFromOrder($order, $style);

        $errors = [];
        if ($inputs['order_qty'] <= 0) {
            $errors['style_id'] = 'This style has no PO quantity in the order.';
        }
        if (TnaPlan::where('order_id', $order->id)->where('style_id', $style->id)->where('status', '!=', 'cancelled')->exists()) {
            $errors['style_id'] = 'This order + style already has a T&A.';
        }
        $shipment = $inputs['shipment_date'] ?? (isset($data['shipment_date']) ? Carbon::parse($data['shipment_date']) : null);
        if (! $shipment) {
            $errors['shipment_date'] = 'The PO lines have no shipment date — enter one.';
        }
        $smv = $inputs['smv'] ?? ($data['smv'] ?? null);
        if (! $smv) {
            $errors['smv'] = 'No bulletin / style SMV for this style — enter the SMV (or make a bulletin first).';
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        $plan = DB::transaction(function () use ($data, $inputs, $shipment, $smv, $numbers) {
            $plan = TnaPlan::create([
                'tna_no' => $numbers->next('tna', TnaPlan::class, 'tna_no'),
                'order_id' => $data['order_id'],
                'style_id' => $data['style_id'],
                'tna_template_id' => $data['tna_template_id'],
                'bulletin_id' => $inputs['bulletin_id'],
                'order_qty' => $inputs['order_qty'],
                'shipment_date' => $shipment,
                'smv' => $smv,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $this->planner->calculate($plan, Line::whereIn('id', $data['line_ids'])->get());
            $this->planner->generateTasks($plan);

            return $plan;
        });

        return redirect()->route('msfl.tna.show', $plan)->with(
            $plan->is_feasible ? 'success' : 'error',
            $plan->is_feasible ? 'T&A ' . $plan->tna_no . ' created.' : 'T&A ' . $plan->tna_no . ' created, but the dates are not achievable — see the capacity box'
        );
    }

    public function show(TnaPlan $tna): View
    {
        $this->authorize('msfl_tna.view');

        if ($tna->isEditable()) {
            $this->planner->syncAutoActuals($tna);
        }

        $tna->load(['order.buyer', 'style.washType', 'template', 'bulletin', 'lines', 'tasks.responsible', 'creator']);
        $conflicts = $this->planner->conflicts($tna);
        $shortfall = $tna->is_feasible ? null : $this->planner->shortfall($tna);
        $users = Lookups::merchandisers();
        $lines = Lookups::lines();

        return view('merchandising-sfl::admin.tna.show', compact('tna', 'conflicts', 'shortfall', 'users', 'lines'));
    }

    /** Save the step grid: revised / actual dates, N/A, responsible, remarks. */
    public function updateTasks(Request $request, TnaPlan $tna): RedirectResponse
    {
        $this->authorize('msfl_tna.edit');
        abort_unless($tna->isEditable(), 403);

        $rows = $request->validate([
            'tasks' => ['required', 'array'],
            'tasks.*.revised_date' => ['nullable', 'date'],
            'tasks.*.actual_date' => ['nullable', 'date'],
            'tasks.*.is_na' => ['nullable', 'boolean'],
            'tasks.*.responsible_id' => ['nullable', Rule::exists('users', 'id')],
            'tasks.*.remarks' => ['nullable', 'string', 'max:255'],
        ])['tasks'];

        DB::transaction(function () use ($tna, $rows) {
            foreach ($tna->tasks()->whereIn('id', array_keys($rows))->get() as $task) {
                $row = $rows[$task->id];
                $task->update([
                    'revised_date' => $row['revised_date'] ?? null,
                    'actual_date' => $row['actual_date'] ?? null,
                    'is_na' => (bool) ($row['is_na'] ?? false),
                    'responsible_id' => $row['responsible_id'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'T&A updated.');
    }

    /** Refresh qty / shipment / SMV from the order (and optionally change lines), then re-plan pending steps. */
    public function recalculate(Request $request, TnaPlan $tna): RedirectResponse
    {
        $this->authorize('msfl_tna.edit');
        abort_unless($tna->isEditable(), 403);

        $data = $request->validate([
            'line_ids' => ['nullable', 'array'],
            'line_ids.*' => [Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($tna, $data) {
            $lines = ! empty($data['line_ids']) ? Line::whereIn('id', $data['line_ids'])->get() : null;
            $this->planner->recalculate($tna, $lines);
        });

        $tna->refresh();

        return back()->with(
            $tna->is_feasible ? 'success' : 'error',
            $tna->is_feasible ? 'Recalculated — pending steps moved to the new dates.' : 'Recalculated, but the dates are still not achievable'
        );
    }

    public function changeStatus(Request $request, TnaPlan $tna): RedirectResponse
    {
        $this->authorize('msfl_tna.edit');
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(TnaPlan::STATUSES))]])['status'];

        $tna->forceFill(['status' => $status])->save();

        return back()->with('success', 'T&A marked ' . TnaPlan::STATUSES[$status][0] . '.');
    }

    public function destroy(TnaPlan $tna): RedirectResponse
    {
        $this->authorize('msfl_tna.delete');

        $tna->delete();

        return redirect()->route('msfl.tna.index')->with('success', 'T&A deleted.');
    }
}
