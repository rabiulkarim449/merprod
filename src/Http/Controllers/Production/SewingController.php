<?php

namespace ME\MerchandisingSfl\Http\Controllers\Production;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Production\SewingPlan;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Services\SewingBoard;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → Sewing, line by line:
 *   board  — the day's Daily Production sheet (hourly output, DHU, efficiency)
 *   input  — pieces given to a line from the cutting balance (by date, size-wise)
 *   hourly — one hour's output / reject / rework per size, with the line's day
 *            plan (target, hours, SMV, operators, helpers)
 * Entries are ordinary sewing production entries (line_id, hour_slot), so
 * ProductionFlow balances, status, reports and T&A count them.
 */
class SewingController extends Controller
{
    public function __construct(private readonly ProductionFlow $flow, private readonly SewingBoard $board)
    {
    }

    /** The board, with the Line Input and Hourly Output modals. */
    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_entry.list');
        $date = $this->date($request);

        return view('merchandising-sfl::admin.production.sewing.index', [
            'date' => $date,
            'board' => $this->board->rows($date, $request->integer('line_id') ?: null),
            'lines' => Lookups::lines(),
            'slots' => ProductionFlow::hourSlots(),
            'breakHour' => ProductionFlow::breakHour(),
        ] + ($request->user()?->can('msfl_prod_entry.add') ? $this->modalData($date, $request) : []));
    }

    /** Data of the two modals: POs with their size balances, the day's plans and plan defaults. */
    private function modalData(Carbon $date, Request $request): array
    {
        $pos = $this->pos();
        $lines = Lookups::lines();

        return [
            'pos' => $pos,
            'sizeNames' => Size::query()->pluck('name', 'id'),
            // po => size => [ordered, available (cutting balance), wip, pass]
            'sizeRows' => $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $this->sizeRows($po)]),
            'plans' => SewingPlan::query()->whereDate('plan_date', $date)->get()
                ->mapWithKeys(fn ($p) => [$p->line_id . '|' . $p->order_po_id => $p->only(['target', 'working_hours', 'smv', 'operators', 'helpers'])]),
            'defaults' => $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $lines->mapWithKeys(fn ($l) => [$l->id => $this->board->defaults($po, $l)])]),
            'selectedHour' => (int) $request->query('hour', $this->currentSlot()),
        ];
    }

    public function print(Request $request): View
    {
        $this->authorize('msfl_prod_entry.list');
        $date = $this->date($request);

        return view('merchandising-sfl::admin.production.sewing.print', [
            'date' => $date,
            'board' => $this->board->rows($date, $request->integer('line_id') ?: null),
            'slots' => ProductionFlow::hourSlots(),
            'breakHour' => ProductionFlow::breakHour(),
        ]);
    }

    /** Daily Hourly Production Report: per line × PO, output / efficiency / DHU hour by hour. */
    public function hourlyReport(Request $request): View
    {
        $this->authorize('msfl_prod_entry.list');
        $date = $this->date($request);

        return view('merchandising-sfl::admin.production.sewing.' . ($request->routeIs('*.print') ? 'hourly-report-print' : 'hourly-report'), [
            'date' => $date,
            'board' => $this->board->rows($date, $request->integer('line_id') ?: null),
            'lines' => Lookups::lines(),
            'slots' => ProductionFlow::hourSlots(),
            'breakHour' => ProductionFlow::breakHour(),
        ]);
    }

    /** Old links: the forms are modals on the board now. */
    public function inputForm(Request $request): RedirectResponse
    {
        return redirect()->route('msfl.production.sewing.index', array_filter(['date' => $request->query('entry_date'), 'modal' => 'input',
            'line' => $request->query('line_id'), 'po' => $request->query('order_po_id')]));
    }

    public function storeInput(Request $request): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');
        $data = $request->validate($this->headerRules() + [
            'sizes' => ['required', 'array'],
            'sizes.*.input_qty' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $po = OrderPo::with('order', 'sizes')->findOrFail($data['order_po_id']);
        $lines = $this->sizeLines($po, $data['sizes'], ['input_qty']);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['sizes' => 'Enter the input of at least one size.']);
        }

        $total = DB::transaction(function () use ($po, $lines, $data) {
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();
            $this->check($po, $lines);
            foreach ($lines as $sizeId => $qty) {
                Entry::create($qty + [
                    'stage' => 'sewing', 'kind' => 'production', 'entry_date' => $data['entry_date'],
                    'order_po_id' => $po->id, 'size_id' => $sizeId, 'line_id' => $data['line_id'],
                    'remarks' => $data['remarks'] ?? null, 'created_by' => auth()->id(),
                ]);
            }

            return $lines->sum('input_qty');
        });

        return redirect()->route('msfl.production.sewing.index', ['date' => $data['entry_date']])
            ->with('success', "Line input saved: {$total} pcs to " . Line::find($data['line_id'])?->name . '.');
    }

    public function hourlyForm(Request $request): RedirectResponse
    {
        return redirect()->route('msfl.production.sewing.index', array_filter(['date' => $request->query('entry_date'), 'modal' => 'hourly',
            'line' => $request->query('line_id'), 'po' => $request->query('order_po_id'), 'hour' => $request->query('hour_slot')]));
    }

    public function storeHourly(Request $request): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');
        $slots = array_diff(array_keys(ProductionFlow::hourSlots()), [ProductionFlow::breakHour()]);
        $data = $request->validate($this->headerRules() + [
            'hour_slot' => ['required', 'integer', Rule::in($slots)],
            'target' => ['required', 'integer', 'min:0'],
            'working_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'smv' => ['required', 'numeric', 'min:0'],
            'operators' => ['required', 'integer', 'min:0'],
            'helpers' => ['required', 'integer', 'min:0'],
            'sizes' => ['required', 'array'],
            'sizes.*.pass_qty' => ['nullable', 'integer', 'min:0'],
            'sizes.*.reject_qty' => ['nullable', 'integer', 'min:0'],
            'sizes.*.rework_qty' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $po = OrderPo::with('order', 'sizes')->findOrFail($data['order_po_id']);
        $lines = $this->sizeLines($po, $data['sizes'], ['pass_qty', 'reject_qty', 'rework_qty']);

        $saved = DB::transaction(function () use ($po, $lines, $data) {
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();
            SewingPlan::query()->updateOrCreate(
                ['plan_date' => $data['entry_date'], 'line_id' => $data['line_id'], 'order_po_id' => $po->id],
                collect($data)->only(['target', 'working_hours', 'smv', 'operators', 'helpers'])->all() + ['created_by' => auth()->id()],
            );
            $this->check($po, $lines);
            foreach ($lines as $sizeId => $qty) {
                Entry::create($qty + [
                    'stage' => 'sewing', 'kind' => 'production', 'entry_date' => $data['entry_date'],
                    'order_po_id' => $po->id, 'size_id' => $sizeId, 'line_id' => $data['line_id'], 'hour_slot' => $data['hour_slot'],
                    'remarks' => $data['remarks'] ?? null, 'created_by' => auth()->id(),
                ]);
            }

            return $lines;
        });

        $hour = ProductionFlow::hourSlots()[$data['hour_slot']];
        $message = $saved->isEmpty()
            ? "Line plan saved ({$hour})."
            : "{$hour}: output {$saved->sum('pass_qty')}, reject {$saved->sum('reject_qty')}, rework {$saved->sum('rework_qty')} saved.";

        // Save & Next Hour: back on the board with the modal open on the next hour.
        return redirect()->route('msfl.production.sewing.index', ['date' => $data['entry_date']] + ($request->boolean('add_another')
                ? ['modal' => 'hourly', 'line' => $data['line_id'], 'po' => $po->id, 'hour' => $this->nextSlot((int) $data['hour_slot'])]
                : []))
            ->with('success', $message);
    }

    // ---------------------------------------------------------------- helpers

    private function headerRules(): array
    {
        return [
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'line_id' => ['required', Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
        ];
    }

    /** Confirmed POs (sewing is on every route). */
    private function pos()
    {
        return Lookups::productionPos()->load('sizes');
    }

    /** size_id => [ordered, available (cutting balance → sewing), wip (in the lines)] for the form. */
    private function sizeRows(OrderPo $po): array
    {
        return $po->sizes->mapWithKeys(function ($s) use ($po) {
            $row = $this->flow->summary($po, null, $s->size_id)['sewing'];

            return [$s->size_id => ['ordered' => (int) $s->qty, 'available' => $row['available'], 'wip' => $row['wip'], 'pass' => $row['pass']]];
        })->all();
    }

    /**
     * The form's size rows with something entered: size_id => qty fields (all four keys).
     * Only the PO's sizes count.
     */
    private function sizeLines(OrderPo $po, array $sizes, array $fields)
    {
        $poSizes = $po->sizes->pluck('size_id')->map(fn ($id) => (int) $id)->all();

        return collect($sizes)
            ->filter(fn ($row, $sizeId) => in_array((int) $sizeId, $poSizes, true))
            ->map(fn ($row) => collect(['input_qty', 'pass_qty', 'rework_qty', 'reject_qty'])
                ->mapWithKeys(fn ($k) => [$k => in_array($k, $fields, true) ? (int) ($row[$k] ?? 0) : 0])->all())
            ->filter(fn ($q) => array_sum($q) > 0)
            ->mapWithKeys(fn ($q, $sizeId) => [(int) $sizeId => $q]);
    }

    /** Balance check of every size row (size and PO total), inside the lock. */
    private function check(OrderPo $po, $lines): void
    {
        if (($po->order->status ?? null) !== 'confirmed') {
            throw ValidationException::withMessages(['order_po_id' => 'Only a confirmed order can go into production.']);
        }
        $errors = [];
        foreach ($lines as $sizeId => $qty) {
            foreach ($this->flow->validate($po, 'sewing', $qty, null, null, $sizeId) as $message) {
                $errors["sizes.{$sizeId}"] = $message;
                break;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function date(Request $request, string $key = 'date'): Carbon
    {
        try {
            return $request->filled($key) ? Carbon::parse($request->input($key))->startOfDay() : today();
        } catch (\Throwable) {
            return today();
        }
    }

    /** The hour slot running now (or the nearest one), break excluded. */
    private function currentSlot(): int
    {
        $slots = array_values(array_diff(array_keys(ProductionFlow::hourSlots()), [ProductionFlow::breakHour()]));
        $now = (int) now()->format('G');
        $past = array_filter($slots, fn ($h) => $h <= $now);

        return $past ? max($past) : $slots[0];
    }

    private function nextSlot(int $hour): int
    {
        $slots = array_values(array_diff(array_keys(ProductionFlow::hourSlots()), [ProductionFlow::breakHour()]));
        $i = array_search($hour, $slots, true);

        return $slots[$i + 1] ?? $hour;
    }
}
