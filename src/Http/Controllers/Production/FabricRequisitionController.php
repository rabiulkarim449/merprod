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
use ME\MerchandisingSfl\Models\Production\FabricRequisition;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Production → Fabric Requisition. Raises a normal Inventory requisition for
 * an order PO; the store approves and issues it in Inventory as usual, and
 * the requested / issued quantities are read back here.
 *
 * From the Buyer Store the requisition carries the Inventory buyer and the
 * style no, so the store can only issue fabric received for that style.
 */
class FabricRequisitionController extends Controller
{
    /** Inventory store types, for an Inventory package older than its InvStore::TYPE_* constants (added 2026-09-28). */
    private const STORE_TYPES_FALLBACK = ['TYPE_BUYER' => 'raw_material', 'TYPE_GENERAL' => 'accessories', 'TYPE_FINISH' => 'finished_goods'];

    private const STORE_TYPES_FALLBACK_LABELS = ['raw_material' => 'Buyer Store', 'accessories' => 'General Store', 'finished_goods' => 'Finish Store'];

    /** InvStore::TYPE_BUYER etc. — the Inventory constant when it exists, else its known value. */
    private static function storeType(string $name): string
    {
        $constant = \ME\SflInventory\Models\InvStore::class . '::' . $name;

        return defined($constant) ? constant($constant) : self::STORE_TYPES_FALLBACK[$name];
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_prod_requisition.list');

        $requisitions = FabricRequisition::query()
            ->with(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'requisition.store', 'requisition.items.item', 'creator'])
            ->when($request->filled('order_po_id'), fn ($q) => $q->where('order_po_id', $request->order_po_id))
            ->latest('id')->paginate($this->perPage(20))->withQueryString();

        return view('merchandising-sfl::admin.production.requisitions.index', [
            'requisitions' => $requisitions,
            'pos' => Lookups::productionPos(),
            'available' => $this->inventoryAvailable(),
        ]);
    }

    /** One requisition: what was asked, approved and issued — and each issue challan with its date. */
    public function show(FabricRequisition $requisition): View
    {
        $this->authorize('msfl_prod_requisition.list');

        $requisition->load(['orderPo.order.buyer', 'orderPo.style', 'orderPo.color', 'creator',
            'requisition.store', 'requisition.department', 'requisition.purpose', 'requisition.requester', 'requisition.approver',
            'requisition.items.item.unit', 'requisition.issues' => fn ($q) => $q->orderBy('issue_date')->orderBy('id'),
            'requisition.issues.items.item.unit', 'requisition.issues.issuer']);

        return view('merchandising-sfl::admin.production.requisitions.show', ['link' => $requisition, 'req' => $requisition->requisition]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('msfl_prod_requisition.add');

        if (! $this->inventoryAvailable()) {
            return redirect()->route('msfl.production.requisitions.index')->with('error', 'The Inventory package is not installed.');
        }

        $pos = Lookups::productionPos();

        return view('merchandising-sfl::admin.production.requisitions.create', [
            'pos' => $pos,
            'received' => $this->receivedByPo($pos),
            'stores' => \ME\SflInventory\Models\InvStore::query()
                ->whereIn('type', [self::storeType('TYPE_BUYER'), self::storeType('TYPE_GENERAL')])
                ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'purposes' => \ME\SflInventory\Models\InvRequisitionPurpose::active()->orderBy('name')->pluck('name', 'code'),
            'departments' => \ME\SflInventory\Models\InvDepartment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => \ME\SflInventory\Models\InvItem::query()->active()->with('unit:id,short_name,name')
                ->orderBy('item_name')->get(['id', 'item_code', 'item_name', 'unit_id']),
            'selectedPo' => $request->integer('order_po_id') ?: null,
            'buyerStoreType' => self::storeType('TYPE_BUYER'),
            'storeTypeLabels' => defined(\ME\SflInventory\Models\InvStore::class . '::TYPE_LABELS') ? \ME\SflInventory\Models\InvStore::TYPE_LABELS : self::STORE_TYPES_FALLBACK_LABELS,
        ]);
    }

    /**
     * po_id => what the Buyer Store received for the PO's style (posted GRNs) and
     * what is left after issues: [[item_id, received, issued, balance]] — picking a
     * PO fills the items with what is left.
     */
    private function receivedByPo($pos): array
    {
        $styleIds = $pos->pluck('style_id')->unique();
        $received = \Illuminate\Support\Facades\DB::table('inv_grn_items as gi')->join('inv_grns as g', 'g.id', '=', 'gi.grn_id')
            ->whereNull('g.deleted_at')->where('g.status', 'posted')->where('g.source_type', 'buyer_supplied')->whereIn('g.msfl_style_id', $styleIds)
            ->groupBy('g.msfl_style_id', 'gi.item_id')->selectRaw('g.msfl_style_id s, gi.item_id i, SUM(gi.received_qty) q')->get();
        $issued = \Illuminate\Support\Facades\DB::table('inv_issue_items as ii')->join('inv_issues as x', 'x.id', '=', 'ii.issue_id')
            ->leftJoinSub(\ME\SflInventory\Models\InvIssueReturnItem::totalsByIssueItem(), 'rtn', 'rtn.issue_item_id', '=', 'ii.id')
            ->whereNull('x.deleted_at')->whereIn('x.msfl_style_id', $styleIds)
            ->groupBy('x.msfl_style_id', 'ii.item_id')->selectRaw('x.msfl_style_id s, ii.item_id i, SUM(ii.issued_qty - COALESCE(rtn.qty, 0)) q')->get()
            ->keyBy(fn ($r) => $r->s . '|' . $r->i);
        $byStyle = $received->groupBy('s')->map(fn ($rows) => $rows->map(function ($r) use ($issued) {
            $out = (float) ($issued[$r->s . '|' . $r->i]->q ?? 0);

            return ['item_id' => (int) $r->i, 'received' => (float) $r->q, 'issued' => $out, 'balance' => max(0, round((float) $r->q - $out, 4))];
        })->values()->all());

        return $pos->mapWithKeys(fn ($po) => [$po->id => $byStyle[$po->style_id] ?? []])->all();
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('msfl_prod_requisition.add');
        abort_unless($this->inventoryAvailable(), 404);

        $data = $request->validate([
            'order_po_id' => ['required', Rule::exists('msfl_order_pos', 'id')],
            'requisition_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('inv_stores', 'id')->whereNull('deleted_at')],
            'department_id' => ['required', Rule::exists('inv_departments', 'id')],
            'requisition_for' => ['nullable', Rule::in(\ME\SflInventory\Models\InvRequisitionPurpose::active()->pluck('code')->all())],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('inv_items', 'id')->whereNull('deleted_at')],
            'items.*.requested_qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = collect($data['items'])->filter(fn ($l) => filled($l['item_id'] ?? null) && (float) ($l['requested_qty'] ?? 0) > 0);
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one item with a quantity.']);
        }

        $po = OrderPo::with(['order.buyer', 'style', 'color'])->findOrFail($data['order_po_id']);
        if (($po->order->status ?? null) !== 'confirmed') {
            throw ValidationException::withMessages(['order_po_id' => 'Only a confirmed order can go into production.']);
        }
        $store = \ME\SflInventory\Models\InvStore::findOrFail($data['store_id']);
        $buyerStore = $store->type === self::storeType('TYPE_BUYER');

        $requisition = DB::transaction(function () use ($data, $lines, $po, $buyerStore) {
            $requisition = \ME\SflInventory\Models\InvRequisition::create([
                'requisition_date' => $data['requisition_date'],
                'department_id' => $data['department_id'],
                'requisition_for' => $data['requisition_for'] ?? null,
                'store_id' => $data['store_id'],
                'buyer_id' => $buyerStore ? app(\ME\SflInventory\Services\MerchandisingLink::class)->inventoryBuyerId($po->order->buyer_id) : null,
                'style' => $buyerStore ? $po->style->style_no : null,
                'order_ref' => $po->label(),
                'msfl_buyer_id' => $po->order->buyer_id,
                'msfl_style_id' => $po->style_id,
                'msfl_order_po_id' => $po->id,
                'requested_by' => auth()->id(),
                'status' => 'pending',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $item = \ME\SflInventory\Models\InvItem::find($line['item_id']);
                [$colorId, $sizeId] = $item?->resolvedVariant(null, null) ?? [null, null];
                $requisition->items()->create([
                    'item_id' => $line['item_id'],
                    'color_id' => $colorId,
                    'size_id' => $sizeId,
                    'requested_qty' => $line['requested_qty'],
                ]);
            }

            FabricRequisition::create(['order_po_id' => $po->id, 'inv_requisition_id' => $requisition->id, 'created_by' => auth()->id()]);

            return $requisition;
        });

        // Same central approval Inventory raises for its own requisitions.
        if (class_exists(\App\Services\ApprovalService::class)) {
            $requisition->loadMissing(['requester', 'department', 'store']);
            app(\App\Services\ApprovalService::class)->request([
                'module' => 'inventory.requisition',
                'approvable' => $requisition,
                'title' => "Requisition Approval - {$requisition->requisition_no}",
                'description' => "{$requisition->requester?->name} requested materials for {$po->label()} from {$requisition->department?->name} / {$requisition->store?->name}.",
                'route_name' => 'inventory.requisitions.approval-form',
                'route_params' => ['requisition' => $requisition->id],
                'requested_by' => auth()->id(),
            ]);
        }

        return redirect()->route('msfl.production.requisitions.index')
            ->with('success', "Requisition {$requisition->requisition_no} sent to {$store->name} for approval and issue.");
    }

    private function inventoryAvailable(): bool
    {
        return class_exists(\ME\SflInventory\Models\InvRequisition::class);
    }
}
