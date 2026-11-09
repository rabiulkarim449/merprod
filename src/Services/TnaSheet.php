<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Bom;
use ME\MerchandisingSfl\Models\CostSheet;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\TnaPlan;
use ME\MerchandisingSfl\Models\TnaTask;

/**
 * The buyer's T&A sheet — 81 columns in 9 merged groups, one row per PO line.
 *
 * Most cells come from where the data was first entered, never retyped:
 *   order / inquiry / style / PO (+ its buyer revisions), cost sheet, BOM,
 *   samples, the T&A plan's dates, Inventory (Buyer Store GRNs: fabric
 *   consignments, trims in-house) and the PCD check. Columns with no
 *   earlier source are T&A tasks (entered on the T&A page).
 */
class TnaSheet
{
    /** Trims columns → words looked for in the received item's name / category. */
    private const TRIMS = [
        'thread' => ['thread'], 'zipper' => ['zipper', 'zip'], 'main_label' => ['main label'], 'size_label' => ['size label'],
        'care_label' => ['care label'], 'elastic' => ['elastic'], 'button_sewing' => ['button'], 'velcro' => ['velcro'],
        'price_tag' => ['price tag', 'hang tag', 'hangtag'], 'price_sticker' => ['sticker'], 'button_finishing' => ['button'],
        'cord' => ['cord'], 'poly' => ['poly'], 'carton' => ['carton'],
    ];

    private array $cache = [];

    /** group => [key => [label, source]]; source: order|po|sample|tna|fabric|inventory|pcd */
    public function groups(): array
    {
        return [
            'Order Status' => [
                'inquiry_date' => ['Inquiry Given date', 'order'], 'merchant' => ['Merchant Name', 'order'],
                'confirm_due' => ['Order confirmation due date', 'order'], 'factory' => ['Allocated Fty', 'order'],
            ],
            'Style Detail' => [
                'buyer' => ['Buyer', 'po'], 'season' => ['Season', 'po'], 'style' => ['Style Name / Number', 'po'],
                'product_type' => ['Product Type', 'po'], 'color' => ['Color', 'po'], 'wash_type' => ['Wash type', 'po'],
                'smv' => ['Cost SMV', 'po'], 'cm' => ['CM', 'po'], 'fob' => ['FOB/FOC', 'po'], 'po_due' => ['PO due date', 'po'],
                'po_no' => ['PO No.', 'po'], 'qty' => ['PO Qty.', 'po'], 'qty_r1' => ['PO Qty revised 1', 'po'], 'qty_r2' => ['PO Qty revised 2', 'po'],
                'pcd' => ['PCD', 'po'], 'fty_pcd' => ['Fty Possible PCD', 'po'], 'pcd_r1' => ['PCD revised 1', 'po'], 'pcd_r2' => ['PCD revised 2', 'po'],
                'ship' => ['Shipment Date', 'po'], 'fty_delivery' => ['Fty Committed Delivery', 'po'],
                'ship_r1' => ['Shipment revised 1', 'po'], 'ship_r2' => ['Shipment revised 2', 'po'], 'ship_mode' => ['Ship Mode', 'po'],
            ],
            'Embellishment' => [
                'print_emb' => ['Print / Emb', 'po'], 'applique' => ['EMB Applique IH', 'po'],
                'studs' => ['Studs/ Stones IH', 'po'], 'heat_seal' => ['Heat seal IH', 'po'],
            ],
            'Sample Status' => [
                'fit_req' => ['Fit Request date', 'sample'], 'fit_sub' => ['Fit Submission', 'sample'], 'fit_app' => ['Fit Approval', 'sample'],
                'fit2_sub' => ['2nd Fit submission', 'sample'], 'fit2_app' => ['2nd Fit Approval', 'sample'],
                'pp_req' => ['1st PP request', 'sample'], 'pp_sub' => ['1st PP submit', 'sample'], 'pp_app' => ['1st PP Approval', 'sample'],
                'task:WASH_STANDARD' => ['Wash Standard Approval', 'tna'], 'task:SHADE_BAND_SUBMIT' => ['Shade band submission', 'tna'],
                'task:LAB_DIP' => ['Shade band Approval', 'tna'], 'task:FILE_HANDOVER' => ['File Hand over Date', 'tna'],
                'task:PULLOUT' => ['Size set Fabric & Trims (Pullout)', 'tna'],
            ],
            'Wash / Pilot Status' => [
                'task:PILOT_STITCHING' => ['Pilot Stitching', 'tna'], 'task:PILOT_WASH' => ['Pilot Wash', 'tna'],
                'task:PILOT_REVIEW' => ['Pilot Review', 'tna'], 'task:PP_MEETING' => ['PP Meeting', 'tna'],
                'remarks:Pre-Production' => ['Remarks', 'tna'],
            ],
            'Fabric Status' => [
                'yy' => ['Fabric YY', 'fabric'], 'requirement' => ['Fabric Requirement', 'fabric'],
                'task:FABRIC_BOOKING' => ['Bulk Fabric PI (Booking date)', 'tna'], 'task:FABRIC_LC' => ['Bulk Fabric LC', 'tna'],
                'mill' => ['Fabric Mill / Supplier', 'fabric'], 'task:FABRIC_XMILL' => ['Bulk Fabric X mill', 'tna'],
                'cons1' => ['Bulk Fabric 1st consignment', 'inventory'], 'cons2' => ['Bulk Fabric 2nd consignment', 'inventory'],
                'cons3' => ['Bulk Fabric 3rd consignment', 'inventory'], 'cons4' => ['Bulk Fabric 4th consignment', 'inventory'],
                'remarks:Material' => ['Remarks', 'tna'],
            ],
            'Sewing Trims Status' => [
                'trim:thread' => ['Thread', 'inventory'], 'trim:zipper' => ['Zipper', 'inventory'], 'trim:main_label' => ['Main Label', 'inventory'],
                'trim:size_label' => ['Size Label', 'inventory'], 'trim:care_label' => ['Care Label', 'inventory'], 'trim:elastic' => ['Elastics', 'inventory'],
                'trim:button_sewing' => ['Buttons', 'inventory'], 'trim:velcro' => ['Velcro', 'inventory'], 'task_remarks:TRIMS_BOOKING' => ['Remarks', 'tna'],
            ],
            'Finishing Trims Status' => [
                'trim:price_tag' => ['Price tag', 'inventory'], 'trim:price_sticker' => ['Price Stickers', 'inventory'],
                'trim:button_finishing' => ['Buttons', 'inventory'], 'trim:cord' => ['Cords', 'inventory'], 'trim:poly' => ['Poly Bags', 'inventory'],
                'task:TRIMS_INHOUSE' => ['Others (all trims in-house)', 'tna'], 'trim:carton' => ['Cartons', 'inventory'],
            ],
            'PCD Pass or Fail' => [
                'pcd_result' => ['PCD Pass or Fail', 'pcd'], 'pcd_reason' => ['Reason', 'pcd'], 'pcd_dept' => ['Responsible Dpt', 'pcd'],
                'pcd_person' => ['Responsible Person', 'pcd'], 'plan_remarks' => ['Remarks', 'tna'],
            ],
        ];
    }

    public const SOURCES = [
        'order' => 'Inquiry / Order', 'po' => 'Order PO / Style / Cost sheet', 'sample' => 'Sample Stages',
        'fabric' => 'BOM', 'inventory' => 'Inventory (Buyer Store receive)', 'tna' => 'Entered in T&A', 'pcd' => 'PCD check',
    ];

    public function columnCount(): int
    {
        return array_sum(array_map('count', $this->groups()));
    }

    /** Load everything a page of PO rows needs in a few queries. */
    public function prepare(Collection $pos): void
    {
        $pos = $pos instanceof \Illuminate\Database\Eloquent\Collection ? $pos : new \Illuminate\Database\Eloquent\Collection($pos->all());
        $pos->loadMissing([
            'order.buyer', 'order.season', 'order.merchandiser', 'order.factory', 'order.inquiry',
            'style.season', 'style.productType', 'style.washType', 'style.inquiry', 'color', 'shipMode', 'revisions',
        ]);
        $styleIds = $pos->pluck('style_id')->unique()->values();
        $orderIds = $pos->pluck('order_id')->unique()->values();

        $this->cache['plans'] = TnaPlan::query()->with(['tasks.responsible'])
            ->whereIn('order_id', $orderIds)->whereIn('style_id', $styleIds)->where('status', '!=', 'cancelled')
            ->get()->keyBy(fn ($p) => $p->order_id . '-' . $p->style_id);

        $this->cache['samples'] = Sample::query()->with('sampleType:id,code')->whereIn('style_id', $styleIds)
            ->orderBy('revision_no')->orderBy('id')->get()->groupBy('style_id');

        $this->cache['boms'] = Bom::query()->with(['items.item', 'items.supplier'])->whereIn('style_id', $styleIds)
            ->where('status', 'approved')->orderByDesc('version')->get()->groupBy('style_id');

        $this->cache['costs'] = CostSheet::query()->whereIn('style_id', $styleIds)->where('status', 'approved')
            ->latest('id')->get()->groupBy('style_id');

        $this->cache['grns'] = [];
        $this->cache['consignments'] = [];
        if (class_exists(\ME\SflInventory\Models\InvGrn::class) && $styleIds->isNotEmpty()) {
            // Each posted Buyer Store receive of the style = one fabric consignment.
            $this->cache['consignments'] = DB::table('inv_grns')->whereIn('msfl_style_id', $styleIds)->where('status', 'posted')
                ->where('source_type', 'buyer_supplied')->whereNull('deleted_at')->orderBy('receive_date')->orderBy('id')
                ->get(['msfl_style_id', 'receive_date'])->groupBy('msfl_style_id')->map(fn ($g) => $g->pluck('receive_date')->all())->all();

            $this->cache['grns'] = DB::table('inv_grns as g')
                ->join('inv_grn_items as gi', 'gi.grn_id', '=', 'g.id')
                ->join('inv_items as i', 'i.id', '=', 'gi.item_id')
                ->leftJoin('inv_item_categories as c', 'c.id', '=', 'i.category_id')
                ->whereIn('g.msfl_style_id', $styleIds)->where('g.status', 'posted')->whereNull('g.deleted_at')
                ->orderBy('g.receive_date')
                ->get(['g.msfl_style_id', 'g.receive_date', 'g.id as grn_id', 'i.item_name', 'c.name as category'])
                ->groupBy('msfl_style_id')->all();
        }
    }

    public function planFor(OrderPo $po): ?TnaPlan
    {
        return $this->cache['plans'][$po->order_id . '-' . $po->style_id] ?? null;
    }

    /**
     * key => ['text', 'hint' (plan date of a pending task), 'color', 'source'].
     */
    public function row(OrderPo $po): array
    {
        $plan = $this->planFor($po);
        $tasks = $plan ? $plan->tasks->keyBy('task_code') : collect();
        $inquiry = $po->order->inquiry ?? $po->style->inquiry ?? null;
        $d = fn ($date) => $date ? Carbon::parse($date)->format('d-M-y') : '';
        $yes = fn ($flag) => $flag ? 'YES' : 'NO';

        // PO line + its buyer revisions after confirmation.
        $rev = fn (string $field) => $po->revisions->where('field', $field)->values();
        [$qty0, $qty1, $qty2] = $this->revised((string) $po->po_qty, $rev('po_qty'));
        [$pcd0, $pcd1, $pcd2] = $this->revised($po->pcd_date?->toDateString(), $rev('pcd_date'));
        [$ship0, $ship1, $ship2] = $this->revised($po->shipment_date?->toDateString(), $rev('shipment_date'));

        $samples = $this->cache['samples'][$po->style_id] ?? collect();
        $sample = fn (string $code) => $samples->first(fn ($s) => ($s->sampleType->code ?? null) === $code);
        $approved = fn (string $code) => $samples->first(fn ($s) => ($s->sampleType->code ?? null) === $code && $s->status === 'approved');

        $bom = ($this->cache['boms'][$po->style_id] ?? collect())->first(fn ($b) => ! $b->order_id || (int) $b->order_id === (int) $po->order_id);
        $fabric = $bom?->items->first(fn ($i) => ($i->item->type ?? null) === 'fabric');
        $yy = $fabric ? (float) $fabric->consumption * (1 + (float) $fabric->wastage_percent / 100) : null;
        $cost = ($this->cache['costs'][$po->style_id] ?? collect())->first();

        $grns = collect($this->cache['grns'][$po->style_id] ?? []);
        $consignments = collect($this->cache['consignments'][$po->style_id] ?? [])->values();
        [$pcdResult, $pcdReason, $pcdDept, $pcdPerson] = $this->pcdCheck($plan, $tasks);

        $fixed = [
            'inquiry_date' => $d($inquiry?->inquiry_date), 'merchant' => $po->order->merchandiser->name ?? '',
            'confirm_due' => $d($inquiry?->confirmation_due_date), 'factory' => $po->order->factory->name ?? '',
            'buyer' => $po->order->buyer->name ?? '', 'season' => $po->order->season->name ?? $po->style->season->name ?? '',
            'style' => $po->style ? trim($po->style->style_no . ' / ' . $po->style->name) : '',
            'product_type' => $po->style->productType->name ?? '', 'color' => $po->color->name ?? '', 'wash_type' => $po->style->washType->name ?? '',
            'smv' => $this->num($plan?->smv ?? $po->style?->smv, 2), 'cm' => $this->num($cost?->cm_cost ?? $po->style?->confirm_cm, 2),
            'fob' => trim($this->num($po->unit_price, 2) . ' ' . ($po->order->delivery_term ?? '')), 'po_due' => $d($po->order->order_date),
            'po_no' => $po->po_no, 'qty' => $this->num($qty0), 'qty_r1' => $this->num($qty1), 'qty_r2' => $this->num($qty2),
            'pcd' => $d($pcd0), 'fty_pcd' => $d($plan?->pcd_date), 'pcd_r1' => $d($pcd1), 'pcd_r2' => $d($pcd2),
            'ship' => $d($ship0), 'fty_delivery' => $d($plan?->ex_factory_date), 'ship_r1' => $d($ship1), 'ship_r2' => $d($ship2),
            'ship_mode' => $po->shipMode->name ?? '',
            'print_emb' => $yes($po->needs_embroidery), 'applique' => $yes($po->applique_ih), 'studs' => $yes($po->studs_stones_ih), 'heat_seal' => $yes($po->heat_seal_ih),
            'fit_req' => $d($sample('FIT')?->request_date), 'fit_sub' => $d($sample('FIT')?->submit_date), 'fit_app' => $d($approved('FIT')?->decision_date),
            'fit2_sub' => $d($sample('FIT2')?->submit_date), 'fit2_app' => $d($approved('FIT2')?->decision_date),
            'pp_req' => $d($sample('PP')?->request_date), 'pp_sub' => $d($sample('PP')?->submit_date), 'pp_app' => $d($approved('PP')?->decision_date),
            'yy' => $yy !== null ? rtrim(rtrim(number_format($yy, 3, '.', ''), '0'), '.') : '',
            'requirement' => $yy !== null ? number_format($yy * $po->po_qty, 0) : '',
            'mill' => $fabric?->supplier->name ?? '',
            'cons1' => $d($consignments[0] ?? null), 'cons2' => $d($consignments[1] ?? null), 'cons3' => $d($consignments[2] ?? null), 'cons4' => $d($consignments[3] ?? null),
            'pcd_result' => $pcdResult, 'pcd_reason' => $pcdReason, 'pcd_dept' => $pcdDept, 'pcd_person' => $pcdPerson,
            'plan_remarks' => (string) ($plan->remarks ?? ''),
        ];

        $cells = [];
        foreach ($this->groups() as $columns) {
            foreach ($columns as $key => [$label, $source]) {
                $cells[$key] = match (true) {
                    str_starts_with($key, 'task:') => $this->taskCell($tasks->get(substr($key, 5)), $plan),
                    str_starts_with($key, 'remarks:') => ['text' => $tasks->where('group_name', substr($key, 8))->pluck('remarks')->filter()->implode('; '), 'hint' => null, 'color' => 'default', 'source' => 'tna'],
                    str_starts_with($key, 'task_remarks:') => ['text' => (string) ($tasks->get(substr($key, 13))?->remarks ?? ''), 'hint' => null, 'color' => 'default', 'source' => 'tna'],
                    str_starts_with($key, 'trim:') => ['text' => $d($this->trimDate($grns, substr($key, 5))), 'hint' => null, 'color' => 'blue', 'source' => 'inventory'],
                    default => ['text' => (string) ($fixed[$key] ?? ''), 'hint' => null, 'color' => $this->fixedColor($key, $pcdResult), 'source' => $source],
                };
            }
        }

        return $cells;
    }

    private function taskCell(?TnaTask $task, ?TnaPlan $plan): array
    {
        if (! $task) {
            return ['text' => $plan ? '' : '', 'hint' => null, 'color' => 'grey', 'source' => 'tna'];
        }
        if ($task->is_na) {
            return ['text' => 'N/A', 'hint' => null, 'color' => 'grey', 'source' => 'tna'];
        }
        $due = $task->revised_date ?? $task->plan_date;
        $color = $task->actual_date
            ? ($due && $task->actual_date->gt($due) ? 'amber' : 'green')
            : ($due && $due->isPast() ? 'red' : ($due && now()->diffInDays($due, false) <= 3 ? 'amber' : 'default'));

        return ['text' => $task->actual_date?->format('d-M-y') ?? '', 'hint' => $task->actual_date ? null : $due?->format('d-M-y'), 'color' => $color, 'source' => $task->auto_source !== 'none' ? 'auto' : 'tna'];
    }

    private function fixedColor(string $key, string $pcdResult): string
    {
        if ($key === 'pcd_result') {
            return ['PASS' => 'green', 'FAIL' => 'red'][$pcdResult] ?? 'default';
        }

        return $key === 'plan_remarks' ? 'default' : 'blue';
    }

    /** [original, revised 1, revised 2 (latest)] from the current value and its revision log. */
    private function revised(?string $current, Collection $revisions): array
    {
        if ($revisions->isEmpty()) {
            return [$current, null, null];
        }

        return [$revisions->first()->old_value, $revisions->first()->new_value, $revisions->count() > 1 ? $revisions->last()->new_value : null];
    }

    /** First Buyer Store receive of a trim, matched by item / category name. */
    private function trimDate(Collection $grns, string $trim): ?string
    {
        $words = self::TRIMS[$trim] ?? [];
        $hit = $grns->first(function ($row) use ($words) {
            $text = mb_strtolower($row->item_name . ' ' . $row->category);
            foreach ($words as $w) {
                if (str_contains($text, $w)) {
                    return true;
                }
            }

            return false;
        });

        return $hit?->receive_date;
    }

    /**
     * PCD check: PASS when cutting started and every mandatory task due before
     * the PCD is done; FAIL when cutting started with any of them open, or the
     * PCD date passed without cutting; PENDING before that. Reason / department / person name what is missing.
     *
     * @return array{0:string,1:string,2:string,3:string}
     */
    private function pcdCheck(?TnaPlan $plan, Collection $tasks): array
    {
        if (! $plan || ! $plan->pcd_date) {
            return ['', '', '', ''];
        }

        $missing = $tasks->filter(fn (TnaTask $t) => $t->is_mandatory && ! $t->is_na && ! $t->actual_date
            && $t->anchor === 'pcd' && (int) $t->offset_days < 0);
        $cut = $tasks->get('PCD')?->actual_date;
        if (! $cut && $plan->pcd_date->isFuture() && $missing->isEmpty()) {
            return ['PENDING', '', '', ''];
        }
        if ($missing->isEmpty() && $cut) {
            return ['PASS', '', '', ''];
        }
        if ($cut) {
            // Cutting started with mandatory pre-PCD work still open.
            return ['FAIL', 'Cut started before: ' . $missing->pluck('task_name')->implode(', '), $missing->pluck('group_name')->unique()->implode(', '), $missing->map(fn ($t) => $t->responsible->name ?? null)->filter()->unique()->implode(', ')];
        }
        if ($plan->pcd_date->isFuture()) {
            return ['PENDING', 'Pending: ' . $missing->pluck('task_name')->implode(', '), $missing->pluck('group_name')->unique()->implode(', '), $missing->map(fn ($t) => $t->responsible->name ?? null)->filter()->unique()->implode(', ')];
        }

        $reason = $missing->pluck('task_name')->when(! $cut, fn ($c) => $c->push('Cutting not started'))->implode(', ');

        return ['FAIL', $reason, $missing->pluck('group_name')->unique()->implode(', '), $missing->map(fn ($t) => $t->responsible->name ?? null)->filter()->unique()->implode(', ')];
    }

    private function num($value, int $decimals = 0): string
    {
        return $value === null || $value === '' ? '' : number_format((float) $value, $decimals);
    }
}
