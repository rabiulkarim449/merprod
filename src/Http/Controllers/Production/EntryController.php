<?php

namespace ME\MerchandisingSfl\Http\Controllers\Production;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Controllers\Controller;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → Embroidery (part-wise) / Washing / Finishing / Packing (input and output as
 * separate entries) and Buyer QC (in, pass, rework, reject + defect rows). The form shows every
 * size of the picked PO with its balance (like the sewing board); one entry is saved per size.
 */
class EntryController extends Controller
{
    public function __construct(private readonly ProductionFlow $flow)
    {
    }

    public function index(Request $request, string $stage): View
    {
        $this->authorize('msfl_prod_entry.list');

        $entries = Entry::query()->where('stage', $stage)
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'size', 'line.floorLine', 'defects', 'creator'])
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('entry_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('entry_date', '<=', $request->to))
            ->latest('entry_date')->latest('id')->paginate($this->perPage(25))->withQueryString();

        return view('merchandising-sfl::admin.production.entries.index', [
            'stage' => $stage,
            'entries' => $entries,
            'pos' => Lookups::productionPos(),
        ]);
    }

    public function create(Request $request, string $stage): View
    {
        $this->authorize('msfl_prod_entry.add');

        $pos = Lookups::productionPos()->load('sizes.size')->filter(fn (OrderPo $po) => in_array($stage, $this->flow->route($po), true))->values();
        // po => size => balance (size-wise flow); part-wise stages add parts / newPart.
        $balances = $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $this->flow->sizeBalances($po, $stage)]);

        return view('merchandising-sfl::admin.production.entries.create', [
            'stage' => $stage,
            'mode' => $this->mode($request, $stage),
            'pos' => $pos,
            'balances' => $balances,
            // po => size ids in display order (XS → 3XL) and the order qty of each.
            'poSizes' => $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => Size::displaySort($po->sizes->pluck('size')->filter())->pluck('id')->all()]),
            'poOrdered' => $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $po->sizes->pluck('qty', 'size_id')]),
            'sizeNames' => Size::query()->pluck('name', 'id'),
            'lines' => ProductionFlow::usesLine($stage) ? Lookups::lines() : collect(),
            'machines' => ProductionFlow::machines(),
            'selectedPo' => $request->integer('order_po_id') ?: null,
            'partWise' => in_array($stage, ProductionFlow::PART_STAGES, true),
            'poParts' => ProductionFlow::poParts($pos->pluck('id')),
            'garmentParts' => Lookups::garmentParts(),
        ]);
    }

    /**
     * Saves one entry per size from the size table (sizes[size_id][input_qty|pass_qty|rework_qty|reject_qty]);
     * the old one-size form (size_id + qty fields) still works. Every size is checked against its own
     * balance and the PO total — one bad size and nothing is saved.
     */
    public function store(Request $request, string $stage): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.add');

        $usesLine = ProductionFlow::usesLine($stage);
        $partWise = in_array($stage, ProductionFlow::PART_STAGES, true);
        $qtyRule = ['nullable', 'integer', 'min:0'];
        $data = $request->validate([
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'size_id' => ['nullable', Rule::exists('inv_sizes', 'id')],
            'sizes' => ['nullable', 'array'],
            'sizes.*.input_qty' => $qtyRule, 'sizes.*.pass_qty' => $qtyRule, 'sizes.*.rework_qty' => $qtyRule, 'sizes.*.reject_qty' => $qtyRule,
            'part_name' => [$partWise ? 'required' : 'nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'line_id' => [$usesLine ? 'required' : 'nullable', Rule::exists('msfl_lines', 'id')->whereNull('deleted_at')],
            'input_qty' => $qtyRule, 'pass_qty' => $qtyRule, 'rework_qty' => $qtyRule, 'reject_qty' => $qtyRule,
            'remarks' => ['nullable', 'string', 'max:255'],
            'defects' => ['nullable', 'array'],
            'defects.*.size_id' => ['nullable', 'integer'],
            'defects.*.type' => ['nullable', 'in:reject,rework'],
            'defects.*.severity' => ['nullable', 'in:critical,major,minor'],
            'defects.*.part_name' => ['nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'defects.*.machine_id' => ['nullable', 'integer', Rule::exists('inv_machines', 'id')],
            'defects.*.defect' => ['nullable', 'string', 'max:150'],
            'defects.*.qty' => ['nullable', 'integer', 'min:0'],
        ]);

        // Step screens record input / output only — reject & rework go through Production → QC / Rework.
        // Buyer QC (final_qc) is itself a QC and keeps pass / rework / reject with defect rows.
        $isQc = $stage === 'final_qc';
        // Finishing / packing posted without a mode (older scripts, demo seeder) = input + output together.
        $mode = $request->filled('mode') || ! in_array($stage, ['finishing', 'packing'], true) ? $this->mode($request, $stage) : null;
        $fields = $isQc ? ['input_qty', 'pass_qty', 'rework_qty', 'reject_qty'] : match ($mode) {
            'input' => ['input_qty'],
            'output' => ['pass_qty'],
            default => ['input_qty', 'pass_qty'],
        };

        // size_id => [input_qty, pass_qty, rework_qty, reject_qty], only sizes with something entered.
        $bySize = $data['sizes'] ?? (! empty($data['size_id']) ? [$data['size_id'] => $data] : []);
        $rows = collect($bySize)->mapWithKeys(fn ($q, $sizeId) => [(int) $sizeId => collect(['input_qty', 'pass_qty', 'rework_qty', 'reject_qty'])
            ->mapWithKeys(fn ($k) => [$k => in_array($k, $fields, true) ? (int) ($q[$k] ?? 0) : 0])->all()])
            ->filter(fn ($q) => array_sum($q) > 0);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['sizes' => 'Enter the pcs of at least one size.']);
        }

        $po = OrderPo::with(['order', 'sizes'])->findOrFail($data['order_po_id']);
        $poSizeIds = $po->sizes->pluck('size_id')->map(fn ($id) => (int) $id)->all();
        $names = Size::query()->whereIn('id', $rows->keys())->pluck('name', 'id');
        foreach ($rows->keys() as $sizeId) {
            if (! in_array($sizeId, $poSizeIds, true)) {
                throw ValidationException::withMessages(["sizes.{$sizeId}" => 'Size ' . ($names[$sizeId] ?? $sizeId) . ' is not in the PO.']);
            }
        }

        // Buyer QC defect rows, per size (a row without a size belongs to the only size entered).
        $defects = $isQc
            ? collect($data['defects'] ?? [])->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, ['reject', 'rework'], true))
                ->map(fn ($d) => $d + ['size_id' => null])
                ->map(fn ($d) => ['size_id' => (int) ($d['size_id'] ?: ($rows->count() === 1 ? $rows->keys()->first() : 0))] + $d)
                ->groupBy('size_id')
            : collect();
        if ($isQc && $defects->has(0)) {
            throw ValidationException::withMessages(['defects' => 'Pick the size of every reject / rework row.']);
        }
        if ($isQc && ($missing = \ME\MerchandisingSfl\Models\Production\EntryDefect::missingSeverity($defects->flatten(1)))) {
            throw ValidationException::withMessages(['defects' => $missing]);
        }

        $saved = DB::transaction(function () use ($po, $stage, $rows, $defects, $data, $usesLine, $partWise, $names) {
            // Lock the PO so two entries can't both pass the balance check.
            OrderPo::query()->whereKey($po->id)->lockForUpdate()->first();
            if (($po->order->status ?? null) !== 'confirmed') {
                throw ValidationException::withMessages(['order_po_id' => 'Only a confirmed order can go into production.']);
            }

            $saved = collect();
            // One size at a time: each check sees the sizes already saved above it.
            foreach ($rows as $sizeId => $qty) {
                $sizeDefects = $defects->get($sizeId, collect());
                $errors = $this->flow->validate($po, $stage, $qty, null, $data['part_name'] ?? null, $sizeId) + $this->flow->defectErrors($sizeDefects, $qty);
                if ($errors) {
                    throw ValidationException::withMessages(["sizes.{$sizeId}" => 'Size ' . ($names[$sizeId] ?? $sizeId) . ': ' . implode(' ', $errors)]);
                }

                $entry = Entry::create($qty + [
                    'stage' => $stage,
                    'entry_date' => $data['entry_date'],
                    'order_po_id' => $po->id,
                    'size_id' => $sizeId,
                    'part_name' => $partWise ? trim($data['part_name']) : null,
                    'line_id' => $usesLine ? $data['line_id'] : null,
                    'remarks' => $data['remarks'] ?? null,
                    'created_by' => auth()->id(),
                ]);
                foreach ($sizeDefects as $d) {
                    $entry->defects()->create([
                        'type' => $d['type'],
                    'severity' => $d['severity'] ?? null,
                        'part_name' => filled($d['part_name'] ?? null) ? trim($d['part_name']) : null,
                        'machine_id' => $d['machine_id'] ?? null,
                        'defect' => $d['defect'] ?? null,
                        'qty' => (int) $d['qty'],
                    ]);
                }
                $saved->push($entry);
            }

            return $saved;
        });

        $label = ProductionFlow::label($stage);
        $sum = fn ($k) => $saved->sum($k);
        $what = collect(['input_qty' => 'in', 'pass_qty' => 'pass', 'rework_qty' => 'rework', 'reject_qty' => 'reject'])
            ->filter(fn ($l, $k) => $sum($k) > 0)->map(fn ($l, $k) => $l . ' ' . $sum($k))->implode(', ');

        return redirect()->route($request->boolean('add_another') ? 'msfl.production.entries.create' : 'msfl.production.entries.index', ['stage' => $stage] + ($request->boolean('add_another') ? ['order_po_id' => $po->id, 'mode' => $mode] : []))
            ->with('success', "{$label}" . (! empty($data['part_name']) ? " ({$data['part_name']})" : '') . " saved for {$saved->count()} size(s): {$what}.");
    }

    /** Split stages (embroidery, washing, finishing, packing): 'input' or 'output'; null for the rest (Buyer QC, sewing). */
    private function mode(Request $request, string $stage): ?string
    {
        if (! in_array($stage, ProductionFlow::SPLIT_STAGES, true)) {
            return null;
        }

        return $request->input('mode') === 'output' ? 'output' : 'input';
    }

    /**
     * Fixing a wrong quantity — PO / size / part / line stay as entered (changing
     * those would mean re-checking a different balance entirely). Both Input and
     * Pass stay editable regardless of stage: a split-stage (embroidery / washing /
     * finishing / packing) entry normally has only one of them set, but older
     * entries from before stages were split can carry both, and hiding either
     * field here would silently zero it out on save.
     */
    public function edit(string $stage, Entry $entry): View
    {
        $this->authorize('msfl_prod_entry.edit');
        abort_unless($entry->stage === $stage, 404);

        $entry->load(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'size', 'line', 'defects']);

        $split = in_array($stage, ProductionFlow::SPLIT_STAGES, true) ? ProductionFlow::splitLabels($stage) : null;

        return view('merchandising-sfl::admin.production.entries.edit', [
            'stage' => $stage,
            'entry' => $entry,
            'split' => $split,
            'isQc' => $stage === 'final_qc',
            'garmentParts' => Lookups::garmentParts(),
            'machines' => ProductionFlow::machines(),
        ]);
    }

    public function update(Request $request, string $stage, Entry $entry): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.edit');
        abort_unless($entry->stage === $stage, 404);

        $isQc = $stage === 'final_qc';

        $data = $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'input_qty' => ['nullable', 'integer', 'min:0'],
            'pass_qty' => ['nullable', 'integer', 'min:0'],
            'rework_qty' => ['nullable', 'integer', 'min:0'],
            'reject_qty' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'defects' => ['nullable', 'array'],
            'defects.*.type' => ['nullable', 'in:reject,rework'],
            'defects.*.severity' => ['nullable', 'in:critical,major,minor'],
            'defects.*.part_name' => ['nullable', 'string', 'max:100', Rule::exists('msfl_garment_parts', 'name')->whereNull('deleted_at')],
            'defects.*.machine_id' => ['nullable', 'integer', Rule::exists('inv_machines', 'id')],
            'defects.*.defect' => ['nullable', 'string', 'max:150'],
            'defects.*.qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $qty = [
            'input_qty' => (int) ($data['input_qty'] ?? 0),
            'pass_qty' => (int) ($data['pass_qty'] ?? 0),
            'rework_qty' => $isQc ? (int) ($data['rework_qty'] ?? 0) : 0,
            'reject_qty' => $isQc ? (int) ($data['reject_qty'] ?? 0) : 0,
        ];
        $defects = $isQc
            ? collect($data['defects'] ?? [])->filter(fn ($d) => (int) ($d['qty'] ?? 0) > 0 && in_array($d['type'] ?? null, ['reject', 'rework'], true))->values()
            : collect();
        if ($missing = \ME\MerchandisingSfl\Models\Production\EntryDefect::missingSeverity($defects)) {
            throw ValidationException::withMessages(['defects' => $missing]);
        }

        DB::transaction(function () use ($entry, $stage, $qty, $defects, $data) {
            $po = $entry->orderPo()->with('order')->lockForUpdate()->first();
            // Would removing this entry's old values break a later entry? If so, changing them would too.
            if ($error = $this->flow->deleteBlocked($entry)) {
                throw ValidationException::withMessages(['input_qty' => $error]);
            }
            $errors = $this->flow->validate($po, $stage, $qty, $entry->id, $entry->part_name, $entry->size_id);
            $errors += $this->flow->defectErrors($defects, $qty);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $entry->update($qty + ['entry_date' => $data['entry_date'], 'remarks' => $data['remarks'] ?? null]);
            $entry->defects()->delete();
            foreach ($defects as $d) {
                $entry->defects()->create([
                    'type' => $d['type'],
                    'severity' => $d['severity'] ?? null,
                    'part_name' => filled($d['part_name'] ?? null) ? trim($d['part_name']) : null,
                    'machine_id' => $d['machine_id'] ?? null,
                    'defect' => $d['defect'] ?? null,
                    'qty' => (int) $d['qty'],
                ]);
            }
        });

        $label = ProductionFlow::label($stage);

        return redirect()->route('msfl.production.entries.index', ['stage' => $stage])
            ->with('success', "{$label}" . ($entry->part_name ? " ({$entry->part_name})" : '') . " entry updated: in {$entry->input_qty}, pass {$entry->pass_qty}, rework {$entry->rework_qty}, reject {$entry->reject_qty}.");
    }

    public function destroy(string $stage, Entry $entry): RedirectResponse
    {
        $this->authorize('msfl_prod_entry.delete');
        abort_unless($entry->stage === $stage, 404);

        // Deleting this entry's passed pieces mustn't leave the next stage with more than it got.
        if ($error = $this->flow->deleteBlocked($entry)) {
            return back()->with('error', $error);
        }

        $entry->delete();

        return back()->with('success', ProductionFlow::label($stage) . ' entry deleted.');
    }
}
