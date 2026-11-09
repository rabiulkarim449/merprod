<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ME\MerchandisingSfl\Models\CostSheet;
use ME\MerchandisingSfl\Models\DailyTarget;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Cutting;
use ME\MerchandisingSfl\Models\Production\Entry;
use ME\MerchandisingSfl\Models\Production\SewingPlan;
use ME\MerchandisingSfl\Models\Sample;
use ME\MerchandisingSfl\Models\TnaTask;
use ME\MerchandisingSfl\Support\Lookups;

/**
 * Merchandising v2 reports. Each returns
 *   ['headers' => [label, ...], 'align' => [i => 'right'], 'rows' => [[...], ...], 'totals' => [i => value] | null,
 *    'row_classes' => [row index => css class] (subtotal rows, not numbered)]
 * or several such tables as ['sections' => [['title' => …, 'headers' => …, …], …]].
 * Filters come from the request: buyer_id, style_id, from, to, date, stage, line_id.
 */
class Reports
{
    /** key => [title, icon, filters used, description, permission (default msfl_report)] */
    public const LIST = [
        'order-book' => ['Order Book', 'fa-book', ['buyer', 'order_status', 'dates'], 'Every PO line of the orders (all statuses, or pick one): qty, value, dates and how much is packed / in the Finish Store.'],
        'daily-production' => ['Daily Production', 'fa-industry', ['buyer', 'dates', 'stage', 'line'], 'Stage entries day by day: input, pass, rework, reject.'],
        'line-output' => ['Line Wise Output (WIP)', 'fa-list-check', ['date', 'line'], 'One day of the sewing floor: per line and style today\'s input, target, output and what is left in the line (WIP); per line the FOB value and CM of target and output, today and month to date; factory cutting, poly (packing) and shipout against Daily Targets.'],
        'defects' => ['Defect Analysis', 'fa-triangle-exclamation', ['dates', 'stage', 'line'], 'Rejects and rework by stage, part, machine and defect.'],
        'shipment-status' => ['Shipment Status', 'fa-truck-fast', ['buyer', 'dates'], 'POs by shipment date with cut / sewn / packed progress and risk.'],
        'tna-status' => ['T&A Status', 'fa-calendar-check', ['buyer'], 'Open T&A tasks of running plans — overdue and due within 7 days.'],
        'sample-turnaround' => ['Sample Turnaround', 'fa-vial', ['buyer', 'dates'], 'Each sample: requested → submitted → decided, with days taken.'],
        'requisition-details' => ['Requisition Details', 'fa-dolly', ['buyer', 'style', 'dates'], 'Every fabric requisition item by buyer / style / PO: requested, approved, issued — and on which dates the store issued how much.'],
        'requisition-summary' => ['Requisition Summary', 'fa-boxes-stacked', ['buyer', 'style', 'dates'], 'Totals per buyer → style → item: requested, approved, issued, still to issue, first / last issue date.'],
        'lc-status' => ['LC Status', 'fa-file-contract', ['buyer'], 'Every Export LC / Sales Contract: value, PO value, shipped (commercial invoices), balance, last shipment and expiry with days left.', 'msfl_com_report'],
        'export-register' => ['Export Register', 'fa-ship', ['buyer', 'dates'], 'Commercial invoices by date: buyer, LC, POs, qty, value, EXP and B/L.', 'msfl_com_report'],
    ];

    public function run(string $key, Request $r): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from)->startOfDay() : null;
        $to = $r->filled('to') ? Carbon::parse($r->to)->endOfDay() : null;

        return match ($key) {
            'order-book' => $this->orderBook($r, $from, $to),
            'lc-status' => $this->lcStatus($r),
            'export-register' => $this->exportRegister($r, $from, $to),
            'line-output' => $this->lineOutput($r, $r->filled('date') ? Carbon::parse($r->date) : today()),
            'daily-production' => $this->dailyProduction($r, $from ?? today()->subDays(6), $to ?? today()->endOfDay()),
            'defects' => $this->defects($r, $from ?? today()->subDays(29), $to ?? today()->endOfDay()),
            'shipment-status' => $this->shipmentStatus($r, $from ?? today()->subDays(7), $to ?? today()->addDays(60)),
            'tna-status' => $this->tnaStatus($r),
            'sample-turnaround' => $this->sampleTurnaround($r, $from, $to),
            'requisition-details' => $this->requisitionDetails($r, $from, $to),
            'requisition-summary' => $this->requisitionSummary($r, $from, $to),
        };
    }

    /** PO lines of confirmed (running) orders, or of the given order statuses ([] = any status). */
    private function pos(Request $r, array $statuses = ['confirmed'])
    {
        return OrderPo::query()->with(['order.buyer', 'style', 'color'])
            ->whereHas('order', fn ($q) => $q->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
                ->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id)));
    }

    /** po_id => [stage => pass] for the given POs. */
    private function passByStage($poIds): array
    {
        $out = [];
        foreach (Entry::query()->whereIn('order_po_id', $poIds)->selectRaw('order_po_id, stage, SUM(' . ProductionFlow::NET_PASS_SQL . ') p')->groupBy('order_po_id', 'stage')->get() as $row) {
            $out[$row->order_po_id][$row->stage] = (int) $row->p;
        }

        return $out;
    }

    private function fgReceived($poIds): array
    {
        if (! class_exists(\ME\SflInventory\Models\InvFinishedGoodsReceive::class)) {
            return [];
        }

        return DB::table('inv_finished_goods_receives as r')->join('inv_finished_goods_receive_items as ri', 'ri.fg_receive_id', '=', 'r.id')
            ->whereIn('r.msfl_order_po_id', $poIds)->groupBy('r.msfl_order_po_id')
            ->selectRaw('r.msfl_order_po_id po, SUM(ri.quantity) q')->pluck('q', 'po')->map(fn ($q) => (int) $q)->all();
    }

    private function orderBook(Request $r, $from, $to): array
    {
        // All order statuses unless one is picked (the old report showed confirmed orders only).
        $status = array_key_exists((string) $r->order_status, Order::STATUSES) ? [$r->order_status] : [];
        $pos = $this->pos($r, $status)->when($from, fn ($q) => $q->where('shipment_date', '>=', $from))->when($to, fn ($q) => $q->where('shipment_date', '<=', $to))
            ->orderBy('shipment_date')->get();
        $pass = $this->passByStage($pos->pluck('id'));
        $fg = $this->fgReceived($pos->pluck('id'));

        $rows = $pos->map(fn ($po) => [
            $po->order->order_no . ' · ' . $po->po_no, Order::STATUSES[$po->order->status][0] ?? ucfirst((string) $po->order->status), $po->order->buyer->name ?? '', $po->style->style_no ?? '', $po->color->name ?? '',
            $po->po_qty, number_format((float) $po->unit_price, 2), (float) $po->total_value,
            $po->pcd_date?->format('d-M-y') ?? '', $po->shipment_date?->format('d-M-y') ?? '',
            $packed = $pass[$po->id]['packing'] ?? 0, $fg[$po->id] ?? 0, max(0, $po->po_qty - $packed),
        ]);

        return [
            'headers' => ['Order · PO', 'Status', 'Buyer', 'Style', 'Color', 'Qty', 'FOB', 'Value', 'PCD', 'Shipment', 'Packed', 'Finish Store', 'Balance'],
            'align' => [5 => 'right', 6 => 'right', 7 => 'right', 10 => 'right', 11 => 'right', 12 => 'right'],
            'rows' => $rows->all(),
            'totals' => [5 => $rows->sum(5), 7 => $rows->sum(7), 10 => $rows->sum(10), 11 => $rows->sum(11), 12 => $rows->sum(12)],
        ];
    }

    private function dailyProduction(Request $r, $from, $to): array
    {
        $entries = Entry::query()->with(['orderPo.order.buyer', 'orderPo.style', 'size', 'line.floorLine'])
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($r->filled('stage'), fn ($q) => $q->where('stage', $r->stage))
            ->when($r->filled('line_id'), fn ($q) => $q->where('line_id', $r->line_id))
            ->when($r->filled('buyer_id'), fn ($q) => $q->whereHas('orderPo.order', fn ($q) => $q->where('buyer_id', $r->buyer_id)))
            ->orderBy('entry_date')->orderBy('stage')->get();

        $rows = $entries->map(fn ($e) => [
            $e->entry_date->format('d-M-y'), ProductionFlow::label($e->stage) . ($e->kind !== 'production' ? ' — ' . ProductionFlow::KINDS[$e->kind] : ''), $e->line->name ?? '',
            ($e->orderPo->order->order_no ?? '') . ' · ' . ($e->orderPo->po_no ?? ''), $e->orderPo->order->buyer->name ?? '', $e->orderPo->style->style_no ?? '',
            $e->size->name ?? 'All', $e->input_qty, $e->pass_qty, $e->rework_qty, $e->reject_qty,
            ($e->pass_qty + $e->reject_qty) > 0 ? round($e->reject_qty / ($e->pass_qty + $e->reject_qty) * 100, 1) . '%' : '',
        ]);

        return [
            'headers' => ['Date', 'Stage', 'Line', 'Order · PO', 'Buyer', 'Style', 'Size', 'Input', 'Pass', 'Rework', 'Reject', 'Reject %'],
            'align' => [7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right', 11 => 'right'],
            'rows' => $rows->all(),
            'totals' => [7 => $rows->sum(7), 8 => $rows->sum(8), 9 => $rows->sum(9), 10 => $rows->sum(10)],
            'period' => $from->format('d-M-Y') . ' – ' . $to->format('d-M-Y'),
        ];
    }

    private function defects(Request $r, $from, $to): array
    {
        $rows = DB::table('msfl_prod_entry_defects as d')->join('msfl_prod_entries as e', 'e.id', '=', 'd.entry_id')
            ->leftJoin('inv_machines as m', 'm.id', '=', 'd.machine_id')
            ->whereNull('e.deleted_at')->whereBetween('e.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($r->filled('stage'), fn ($q) => $q->where('e.stage', $r->stage))
            ->when($r->filled('line_id'), fn ($q) => $q->where('e.line_id', $r->line_id))
            ->groupBy('e.stage', 'd.part_name', 'm.code', 'm.name', 'd.defect')
            ->selectRaw("e.stage, d.part_name, CONCAT_WS(' — ', m.code, m.name) machine, d.defect,
                SUM(CASE WHEN d.type = 'reject' THEN d.qty ELSE 0 END) rj, SUM(CASE WHEN d.type = 'rework' THEN d.qty ELSE 0 END) rw")
            ->orderByRaw('SUM(d.qty) DESC')->get();
        $checked = Entry::query()->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('stage, SUM(pass_qty + reject_qty) c')->groupBy('stage')->pluck('c', 'stage');
        // Cutting has no production entries: what it checked is what it cut.
        $checked['cutting'] = (int) \ME\MerchandisingSfl\Models\Production\Cutting::query()
            ->whereBetween('cutting_date', [$from->toDateString(), $to->toDateString()])->sum('total_qty');

        $data = $rows->map(fn ($x) => [
            ProductionFlow::label($x->stage), $x->part_name ?: '—', $x->machine ?: '—', $x->defect ?: '—', (int) $x->rj, (int) $x->rw,
            ($checked[$x->stage] ?? 0) > 0 ? round(($x->rj + $x->rw) / $checked[$x->stage] * 100, 2) . '%' : '',
        ]);

        return [
            'headers' => ['Stage', 'Part', 'Machine', 'Defect', 'Reject', 'Rework', '% of checked'],
            'align' => [4 => 'right', 5 => 'right', 6 => 'right'],
            'rows' => $data->all(),
            'totals' => [4 => $data->sum(4), 5 => $data->sum(5)],
            'period' => $from->format('d-M-Y') . ' – ' . $to->format('d-M-Y'),
        ];
    }

    private function shipmentStatus(Request $r, $from, $to): array
    {
        $pos = $this->pos($r)->whereBetween('shipment_date', [$from->toDateString(), $to->toDateString()])->orderBy('shipment_date')->get();
        $pass = $this->passByStage($pos->pluck('id'));
        $cut = \ME\MerchandisingSfl\Models\Production\Cutting::query()->whereIn('order_po_id', $pos->pluck('id'))->groupBy('order_po_id')
            ->selectRaw('order_po_id, SUM(total_qty) q')->pluck('q', 'order_po_id');
        $fg = $this->fgReceived($pos->pluck('id'));

        $rows = $pos->map(function ($po) use ($pass, $cut, $fg) {
            $packed = $pass[$po->id]['packing'] ?? 0;
            $days = (int) today()->diffInDays($po->shipment_date, false);
            $pct = $po->po_qty > 0 ? $packed / $po->po_qty * 100 : 0;
            $status = $pct >= 100 ? 'Packed' : ($days < 0 ? 'Late' : ($days <= 7 && $pct < 80 ? 'At risk' : 'On track'));

            return [
                $po->shipment_date->format('d-M-y'), $days >= 0 ? $days . ' d' : abs($days) . ' d late',
                $po->order->order_no . ' · ' . $po->po_no, $po->order->buyer->name ?? '', $po->style->style_no ?? '',
                $po->po_qty, (int) ($cut[$po->id] ?? 0), $pass[$po->id]['sewing'] ?? 0, $packed, $fg[$po->id] ?? 0, round($pct) . '%', $status,
            ];
        });

        return [
            'headers' => ['Shipment', 'Days', 'Order · PO', 'Buyer', 'Style', 'Qty', 'Cut', 'Sewn', 'Packed', 'Finish Store', 'Packed %', 'Status'],
            'align' => [5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right'],
            'rows' => $rows->all(),
            'totals' => [5 => $rows->sum(5), 6 => $rows->sum(6), 7 => $rows->sum(7), 8 => $rows->sum(8), 9 => $rows->sum(9)],
            'period' => $from->format('d-M-Y') . ' – ' . $to->format('d-M-Y'),
            'status_col' => 11,
        ];
    }

    private function tnaStatus(Request $r): array
    {
        $tasks = TnaTask::query()->with(['plan.order.buyer', 'plan.style', 'responsible'])
            ->whereNull('actual_date')->where('is_na', false)
            ->whereHas('plan', fn ($q) => $q->where('status', 'active')
                ->when($r->filled('buyer_id'), fn ($q) => $q->whereHas('order', fn ($q) => $q->where('buyer_id', $r->buyer_id))))
            ->whereRaw('COALESCE(revised_date, plan_date) <= ?', [today()->addDays(7)->toDateString()])
            ->orderByRaw('COALESCE(revised_date, plan_date)')->get();

        $rows = $tasks->map(function ($t) {
            $due = $t->revised_date ?? $t->plan_date;
            $days = (int) today()->diffInDays($due, false);

            return [
                $t->plan->tna_no ?? '', $t->plan->order->buyer->name ?? '', $t->plan->style->style_no ?? '', $t->group_name, $t->task_name,
                $due->format('d-M-y'), $days < 0 ? abs($days) . ' d overdue' : ($days === 0 ? 'today' : 'in ' . $days . ' d'),
                $t->is_mandatory ? 'Yes' : '', $t->responsible->name ?? '', $days < 0 ? 'Late' : 'At risk',
            ];
        });

        return [
            'headers' => ['T&A', 'Buyer', 'Style', 'Group', 'Task', 'Due', 'When', 'Mandatory', 'Responsible', 'Status'],
            'align' => [], 'rows' => $rows->all(), 'totals' => null, 'status_col' => 9,
        ];
    }

    private function sampleTurnaround(Request $r, $from, $to): array
    {
        $samples = Sample::query()->with(['style', 'buyer', 'sampleType'])
            ->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id))
            ->when($from, fn ($q) => $q->where('request_date', '>=', $from))->when($to, fn ($q) => $q->where('request_date', '<=', $to))
            ->orderByDesc('request_date')->get();

        $rows = $samples->map(fn ($s) => [
            $s->sample_no, $s->buyer->name ?? '', $s->style->style_no ?? '', $s->sampleType->name ?? '', $s->revision_no ?: '',
            $s->request_date?->format('d-M-y') ?? '', $s->submit_date?->format('d-M-y') ?? '', $s->decision_date?->format('d-M-y') ?? '',
            $s->submit_date ? $s->request_date->diffInDays($s->submit_date) : '', $s->decision_date && $s->submit_date ? $s->submit_date->diffInDays($s->decision_date) : '',
            ucfirst(str_replace('_', ' ', $s->status)),
        ]);

        return [
            'headers' => ['Sample', 'Buyer', 'Style', 'Type', 'Rev', 'Requested', 'Submitted', 'Decided', 'Days to submit', 'Buyer days', 'Status'],
            'align' => [8 => 'right', 9 => 'right'], 'rows' => $rows->all(), 'totals' => null, 'status_col' => 10,
        ];
    }

    /**
     * Requisition items raised from Production → Fabric Requisition (Inventory requisitions),
     * with buyer / style / PO / item. Filters: buyer, style, requisition date.
     */
    private function requisitionItems(Request $r, $from, $to)
    {
        return DB::table('msfl_prod_fabric_requisitions as fr')
            ->join('inv_requisitions as rq', 'rq.id', '=', 'fr.inv_requisition_id')
            ->join('inv_requisition_items as ri', 'ri.requisition_id', '=', 'rq.id')
            ->join('msfl_order_pos as po', 'po.id', '=', 'fr.order_po_id')
            ->join('msfl_orders as o', 'o.id', '=', 'po.order_id')
            ->join('msfl_buyers as b', 'b.id', '=', 'o.buyer_id')
            ->join('msfl_styles as st', 'st.id', '=', 'po.style_id')
            ->leftJoin('inv_colors as c', 'c.id', '=', 'po.color_id')
            ->leftJoin('inv_items as it', 'it.id', '=', 'ri.item_id')
            ->leftJoin('inv_units as u', 'u.id', '=', 'it.unit_id')
            ->whereNull('rq.deleted_at')
            ->when($r->filled('buyer_id'), fn ($q) => $q->where('o.buyer_id', $r->buyer_id))
            ->when($r->filled('style_id'), fn ($q) => $q->where('po.style_id', $r->style_id))
            ->when($from, fn ($q) => $q->whereDate('rq.requisition_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('rq.requisition_date', '<=', $to->toDateString()));
    }

    /** requisition_item_id => issue lines [date, issue no, qty] of issues not cancelled. */
    private function issueLines($itemIds)
    {
        return DB::table('inv_issue_items as ii')->join('inv_issues as i', 'i.id', '=', 'ii.issue_id')
            ->whereIn('ii.requisition_item_id', $itemIds)->whereNull('i.deleted_at')->where('i.status', '!=', 'cancelled')
            ->orderBy('i.issue_date')->orderBy('i.id')
            ->get(['ii.requisition_item_id', 'i.issue_date', 'i.issue_no', 'ii.issued_qty'])->groupBy('requisition_item_id');
    }

    private function requisitionDetails(Request $r, $from, $to): array
    {
        $items = $this->requisitionItems($r, $from, $to)
            ->orderByDesc('rq.requisition_date')->orderByDesc('rq.id')
            ->get(['ri.id', 'rq.requisition_no', 'rq.requisition_date', 'rq.status', 'b.name as buyer', 'st.style_no', 'po.po_no', 'o.order_no',
                'c.name as color', 'it.item_code', 'it.item_name', 'u.short_name as unit', 'ri.requested_qty', 'ri.approved_qty', 'ri.issued_qty']);
        $issues = $this->issueLines($items->pluck('id'));
        $q = fn ($v) => round((float) $v, 2);

        $rows = $items->map(fn ($x) => [
            Carbon::parse($x->requisition_date)->format('d-M-y'), $x->requisition_no, $x->buyer, $x->style_no, $x->order_no . ' · ' . $x->po_no . ' · ' . $x->color,
            trim($x->item_code . ' ' . $x->item_name), $x->unit, $q($x->requested_qty), $q($x->approved_qty), $q($x->issued_qty),
            $q(max(0, (float) ($x->approved_qty ?? $x->requested_qty) - (float) $x->issued_qty)),
            ($issues[$x->id] ?? collect())->map(fn ($i) => Carbon::parse($i->issue_date)->format('d-M-y') . ': ' . $q($i->issued_qty) . ' (' . $i->issue_no . ')')->implode(', ') ?: '—',
            ucfirst(str_replace('_', ' ', $x->status)),
        ]);

        return [
            'headers' => ['Req. Date', 'Requisition', 'Buyer', 'Style', 'Order · PO · Color', 'Item', 'Unit', 'Requested', 'Approved', 'Issued', 'To Issue', 'Issued on (date: qty)', 'Status'],
            'align' => [7 => 'right', 8 => 'right', 9 => 'right', 10 => 'right'],
            'rows' => $rows->all(),
            'totals' => [7 => $rows->sum(7), 8 => $rows->sum(8), 9 => $rows->sum(9), 10 => $rows->sum(10)],
            'period' => $from || $to ? ($from?->format('d-M-Y') ?? '…') . ' – ' . ($to?->format('d-M-Y') ?? '…') : null,
        ];
    }

    private function requisitionSummary(Request $r, $from, $to): array
    {
        $items = $this->requisitionItems($r, $from, $to)
            ->get(['ri.id', 'rq.id as req_id', 'b.name as buyer', 'st.style_no', 'it.item_code', 'it.item_name', 'u.short_name as unit',
                'ri.requested_qty', 'ri.approved_qty', 'ri.issued_qty']);
        $issues = $this->issueLines($items->pluck('id'));
        $q = fn ($v) => round((float) $v, 2);

        $rows = $items->groupBy(fn ($x) => $x->buyer . '|' . $x->style_no . '|' . $x->item_code)
            ->map(function ($g) use ($issues, $q) {
                $x = $g->first();
                $dates = $g->flatMap(fn ($i) => $issues[$i->id] ?? [])->pluck('issue_date')->sort()->values();
                $approved = $g->sum(fn ($i) => (float) ($i->approved_qty ?? $i->requested_qty));

                return [
                    $x->buyer, $x->style_no, trim($x->item_code . ' ' . $x->item_name), $x->unit, $g->pluck('req_id')->unique()->count(),
                    $q($g->sum('requested_qty')), $q($approved), $q($g->sum('issued_qty')), $q(max(0, $approved - $g->sum('issued_qty'))),
                    $dates->isEmpty() ? '—' : Carbon::parse($dates->first())->format('d-M-y'),
                    $dates->isEmpty() ? '—' : Carbon::parse($dates->last())->format('d-M-y'),
                ];
            })->sortBy(fn ($row) => $row[0] . $row[1] . $row[2])->values();

        return [
            'headers' => ['Buyer', 'Style', 'Item', 'Unit', 'Requisitions', 'Requested', 'Approved', 'Issued', 'To Issue', 'First Issue', 'Last Issue'],
            'align' => [4 => 'right', 5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right'],
            'rows' => $rows->all(),
            'totals' => [4 => $rows->sum(4), 5 => $rows->sum(5), 6 => $rows->sum(6), 7 => $rows->sum(7), 8 => $rows->sum(8)],
            'period' => $from || $to ? ($from?->format('d-M-Y') ?? '…') . ' – ' . ($to?->format('d-M-Y') ?? '…') : null,
        ];
    }

    /**
     * Daily Line Wise Output (WIP): sewing per line × PO for one day, the line's value
     * (FOB / CM) today and month to date, and factory cutting / poly / shipout.
     * Sewing figures are the Sewing board's (production entries); targets from the Sewing Plan.
     */
    private function lineOutput(Request $r, Carbon $date): array
    {
        $day = $date->toDateString();
        $monthStart = $date->copy()->startOfMonth()->toDateString();
        $lines = Lookups::lines()->when($r->filled('line_id'), fn ($c) => $c->where('id', (int) $r->line_id))->values();
        $key = fn ($x) => $x->line_id . '|' . $x->order_po_id;

        $sewing = Entry::query()->where('stage', 'sewing')->where('kind', 'production')->whereIn('line_id', $lines->pluck('id'))
            ->where('entry_date', '<=', $day)
            ->selectRaw('line_id, order_po_id, MIN(CASE WHEN input_qty > 0 THEN entry_date END) first_in,
                SUM(input_qty) i, SUM(pass_qty) o, SUM(reject_qty) rj,
                SUM(CASE WHEN entry_date = ? THEN input_qty ELSE 0 END) di, SUM(CASE WHEN entry_date = ? THEN pass_qty ELSE 0 END) do_,
                SUM(CASE WHEN entry_date >= ? THEN pass_qty ELSE 0 END) mo', [$day, $day, $monthStart])
            ->groupBy('line_id', 'order_po_id')->get()->keyBy($key);
        $plans = SewingPlan::query()->whereIn('line_id', $lines->pluck('id'))->whereBetween('plan_date', [$monthStart, $day])
            ->selectRaw("line_id, order_po_id, SUM(target) mt, SUM(CASE WHEN plan_date = ? THEN target ELSE 0 END) dt,
                MAX(CASE WHEN plan_date = ? THEN remarks END) remarks", [$day, $day])
            ->groupBy('line_id', 'order_po_id')->get()->keyBy($key);

        $pos = OrderPo::query()->with(['order.buyer', 'style', 'color'])
            ->whereIn('id', $sewing->pluck('order_po_id')->merge($plans->pluck('order_po_id'))->unique())->get()->keyBy('id');
        $cut = Cutting::query()->whereIn('order_po_id', $pos->keys())->where('cutting_date', '<=', $day)
            ->groupBy('order_po_id')->selectRaw('order_po_id, SUM(total_qty) q')->pluck('q', 'order_po_id');
        // CM per piece from the style's approved cost sheet (per dozen).
        $cm = CostSheet::query()->where('status', 'approved')->whereIn('style_id', $pos->pluck('style_id')->unique())->orderBy('id')
            ->get(['style_id', 'cm_cost'])->keyBy('style_id')->map(fn ($c) => (float) $c->cm_cost / 12);
        $target = DailyTarget::query()->active()->whereBetween('target_date', [$monthStart, $day])->get();
        $today = $target->first(fn ($t) => $t->target_date->toDateString() === $day);

        // ── Sewing: line × style ──
        $sum = fn ($rows, $i) => array_sum(array_column($rows, $i));
        $top = [];
        $topClasses = [];
        $grand = [];
        $money = [];
        foreach ($lines as $line) {
            $rows = [];
            $value = array_fill_keys(['dt', 'dtv', 'do', 'dov', 'docm', 'mt', 'mtv', 'mo', 'mov', 'mocm'], 0.0);
            $keys = $sewing->keys()->merge($plans->keys())->unique()->filter(fn ($k) => str_starts_with($k, $line->id . '|'))
                ->sortBy(fn ($k) => $sewing[$k]->first_in ?? '9999');
            foreach ($keys as $k) {
                $s = $sewing->get($k);
                $p = $plans->get($k);
                $po = $pos->get((int) explode('|', $k)[1]);
                if (! $po) {
                    continue;
                }
                $fob = (float) $po->unit_price;
                $cmPc = $cm[$po->style_id] ?? 0;
                $dayTarget = (int) ($p->dt ?? 0);
                $dayOut = (int) ($s->do_ ?? 0);
                $value['dt'] += $dayTarget;
                $value['dtv'] += $dayTarget * $fob;
                $value['do'] += $dayOut;
                $value['dov'] += $dayOut * $fob;
                $value['docm'] += $dayOut * $cmPc;
                $value['mt'] += (int) ($p->mt ?? 0);
                $value['mtv'] += (int) ($p->mt ?? 0) * $fob;
                $value['mo'] += (int) ($s->mo ?? 0);
                $value['mov'] += (int) ($s->mo ?? 0) * $fob;
                $value['mocm'] += (int) ($s->mo ?? 0) * $cmPc;

                $wip = (int) ($s->i ?? 0) - (int) ($s->o ?? 0) - (int) ($s->rj ?? 0);
                // Finished in this line and nothing today: leave it out.
                if ($wip <= 0 && ! $dayTarget && ! $dayOut && ! (int) ($s->di ?? 0)) {
                    continue;
                }
                $rows[] = [
                    $line->code, $po->order->buyer->name ?? '', $po->style->style_no ?? '', $po->color->name ?? '',
                    (int) $po->po_qty, (int) ($cut[$po->id] ?? 0), $s?->first_in ? Carbon::parse($s->first_in)->format('d-M-y') : '',
                    (int) ($s->di ?? 0), (int) ($s->i ?? 0), $dayTarget, $dayOut, $dayOut - $dayTarget,
                    $dayTarget ? round(($dayOut - $dayTarget) / $dayTarget * 100) . '%' : '', (int) ($s->o ?? 0), $wip, $p->remarks ?? '',
                ];
            }
            if ($value['mt'] || $value['mo'] || $value['dt']) {
                $money[] = [$line, $value];
            }
            if (! $rows) {
                continue;
            }
            array_push($top, ...$rows);
            $grand = array_merge($grand, $rows);
            $topClasses[count($top)] = 'table-secondary font-weight-bold';
            $top[] = $this->lineOutputTotal($line->code . ' Total', $rows, $sum);
        }
        if ($grand) {
            $topClasses[count($top)] = 'table-success font-weight-bold';
            $top[] = $this->lineOutputTotal('Grand Total', $grand, $sum);
        }

        // ── Planning output: value per line ──
        $required = (float) ($today->line_required_value ?? 0);
        $bottom = [];
        foreach ($money as [$line, $v]) {
            $bottom[] = [
                $line->code, $v['mo'] ? round($v['mocm'] / $v['mo'], 2) : '', $v['mo'] ? round($v['mov'] / $v['mo'], 2) : '',
                (int) $v['dt'], round($v['dtv'], 2), (int) $v['do'], round($v['dov'], 2), round($v['docm'], 2),
                $required ? round($required, 2) : '', $required ? round($v['dov'] - $required, 2) : '',
                (int) $v['mt'], round($v['mtv'], 2), (int) $v['mo'], round($v['mov'], 2), round($v['mocm'], 2), (int) ($v['mo'] - $v['mt']),
                round($v['mov'] - $v['mtv'], 2),
            ];
        }
        $bottomClasses = [];
        if ($bottom) {
            $total = ['Total', '', ''];
            foreach (range(3, 16) as $i) {
                $total[$i] = $i === 8 || $i === 9 ? ($required ? round($sum($bottom, $i), 2) : '') : (is_float($bottom[0][$i]) ? round($sum($bottom, $i), 2) : (int) $sum($bottom, $i));
            }
            $bottomClasses[count($bottom)] = 'table-success font-weight-bold';
            $bottom[] = $total;
        }

        // ── Factory: cutting, poly (packing), shipout ──
        $cutQty = fn ($from) => (int) Cutting::query()->whereBetween('cutting_date', [$from, $day])->sum('total_qty');
        $packQty = fn ($from) => (int) Entry::query()->where('stage', 'packing')->whereBetween('entry_date', [$from, $day])
            ->sum(DB::raw(ProductionFlow::NET_PASS_SQL));
        $ship = fn ($from) => $this->shipout($from, $day);
        $factory = [];
        foreach ([
            ['Cutting', 'cutting_target', $cutQty($day), $cutQty($monthStart), null, null],
            ['Poly (Packing)', 'packing_target', $packQty($day), $packQty($monthStart), null, null],
            ['Shipout', null, ($d = $ship($day))['qty'], ($m = $ship($monthStart))['qty'], $d['value'], $m['value']],
        ] as [$label, $field, $dayQty, $monthQty, $dayValue, $monthValue]) {
            $dayTarget = $field ? (int) ($today->{$field} ?? 0) : null;
            $monthTarget = $field ? (int) $target->sum($field) : null;
            $factory[] = [
                $label, $dayTarget ?? '', $dayQty, $field ? $dayQty - $dayTarget : '', $monthTarget ?? '', $monthQty, $field ? $monthQty - $monthTarget : '',
                $dayValue === null ? '' : round($dayValue, 2), $monthValue === null ? '' : round($monthValue, 2),
            ];
        }

        $right = fn (array $cols) => array_fill_keys($cols, 'right');

        return [
            'period' => $date->format('d-M-Y') . ' (month from ' . Carbon::parse($monthStart)->format('d-M') . ')',
            'sections' => [
                [
                    'title' => 'Sewing — line wise output (pcs)',
                    'headers' => ['Line', 'Buyer', 'Style', 'Color', 'Order Qty', 'Cut Qty', 'Input Date', 'Day Input', 'Total Input', 'Day Target', 'Day Output', 'Short / Ex', 'Short / Ex %', 'Total Output', 'Line WIP', 'Remarks'],
                    'align' => $right([4, 5, 7, 8, 9, 10, 11, 12, 13, 14]),
                    'rows' => $top, 'row_classes' => $topClasses, 'totals' => null,
                ],
                [
                    'title' => 'Planning output — value per line (FOB, CM)',
                    'headers' => ['Line', 'CM / pc', 'FOB / pc', 'Today Target', 'Today Target Value', 'Today Output', 'Today Output Value', 'Today Output CM',
                        'Required Value', 'Short / Ex Value (Day)', 'Month Target', 'Month Target Value', 'Month Output', 'Month Output Value', 'Month Output CM',
                        'Short / Ex (Month)', 'Short / Ex Value (Month)'],
                    'align' => $right(range(1, 16)),
                    'rows' => $bottom, 'row_classes' => $bottomClasses, 'totals' => null,
                ],
                [
                    'title' => 'Factory — cutting, poly and shipout (pcs)',
                    'headers' => ['', 'Day Target', 'Day Achieve', 'Day Short / Ex', 'Month Target', 'Month Achieve', 'Month Short / Ex', 'Day Value', 'Month Value'],
                    'align' => $right(range(1, 8)),
                    'rows' => $factory, 'totals' => null,
                ],
            ],
        ];
    }

    private function lineOutputTotal(string $label, array $rows, \Closure $sum): array
    {
        $dayTarget = $sum($rows, 9);
        $dayOut = $sum($rows, 10);

        return [$label, '', '', '', $sum($rows, 4), $sum($rows, 5), '', $sum($rows, 7), $sum($rows, 8), $dayTarget, $dayOut, $dayOut - $dayTarget,
            $dayTarget ? round(($dayOut - $dayTarget) / $dayTarget * 100) . '%' : '', $sum($rows, 13), $sum($rows, 14), ''];
    }

    /** Shipped qty and FOB value (Inventory shipment lines linked to a PO) between two dates. */
    private function shipout(string $from, string $to): array
    {
        if (! Schema::hasColumn('inv_shipment_items', 'msfl_order_po_id')) {
            return ['qty' => 0, 'value' => 0.0];
        }
        $row = DB::table('inv_shipment_items as si')->join('inv_shipments as s', 's.id', '=', 'si.shipment_id')
            ->join('msfl_order_pos as po', 'po.id', '=', 'si.msfl_order_po_id')
            ->whereNull('s.deleted_at')->whereBetween('s.shipment_date', [$from, $to])
            ->selectRaw('SUM(si.quantity) q, SUM(si.quantity * po.unit_price) v')->first();

        return ['qty' => (int) ($row->q ?? 0), 'value' => (float) ($row->v ?? 0)];
    }

    /** Export LCs with their shipped value and days to last shipment / expiry. */
    private function lcStatus(Request $r): array
    {
        $status = app(\ME\MerchandisingSfl\Services\Commercial\LcStatus::class);
        $lcs = \ME\MerchandisingSfl\Models\Commercial\ExportLc::query()->with(['buyer', 'currency', 'pos'])
            ->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id))
            ->orderByRaw("FIELD(status, 'active', 'draft', 'closed')")->orderBy('expiry_date')->get();

        $rows = $lcs->map(function ($lc) use ($status) {
            $f = $status->figures($lc);
            $state = $lc->status !== 'active' ? ucfirst($lc->status)
                : ($f['expiry_days'] !== null && $f['expiry_days'] < 0 ? 'Late' : ($f['expiry_days'] !== null && $f['expiry_days'] <= 30 ? 'At risk' : 'Active'));

            return [
                $lc->lc_no, strtoupper($lc->type) . ' ' . $lc->buyer_lc_no, $lc->buyer->name ?? '', $lc->currency->code ?? '',
                (float) $lc->lc_value, $f['po_value'], $f['shipped'], $f['balance'], $lc->pos->count(), $f['invoices'],
                $lc->last_shipment_date?->format('d-M-y') ?? '', $lc->expiry_date?->format('d-M-y') ?? '',
                $f['expiry_days'] === null ? '' : ($f['expiry_days'] < 0 ? abs($f['expiry_days']) . ' d expired' : $f['expiry_days'] . ' d'), $state,
            ];
        });

        return [
            'headers' => ['LC No', 'Buyer LC / SC', 'Buyer', 'Cur', 'LC Value', 'PO Value', 'Shipped', 'Balance', 'POs', 'Invoices', 'Last Ship', 'Expiry', 'Expiry in', 'Status'],
            'align' => [4 => 'right', 5 => 'right', 6 => 'right', 7 => 'right', 8 => 'right', 9 => 'right'],
            'rows' => $rows->all(),
            'totals' => [4 => round($rows->sum(4), 2), 5 => round($rows->sum(5), 2), 6 => round($rows->sum(6), 2), 7 => round($rows->sum(7), 2)],
            'status_col' => 13,
        ];
    }

    /** Commercial invoices by date. */
    private function exportRegister(Request $r, $from, $to): array
    {
        $invoices = \ME\MerchandisingSfl\Models\Commercial\Invoice::query()->with(['buyer', 'exportLc.currency', 'shipMode', 'lines.orderPo.style'])
            ->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id))
            ->when($from, fn ($q) => $q->whereDate('invoice_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('invoice_date', '<=', $to))
            ->orderBy('invoice_date')->orderBy('id')->get();

        $rows = $invoices->map(fn ($i) => [
            $i->invoice_date->format('d-M-y'), $i->invoice_no, $i->buyer->name ?? '', ($i->exportLc->lc_no ?? '') . ' · ' . ($i->exportLc->buyer_lc_no ?? ''),
            $i->lines->map(fn ($l) => ($l->orderPo->po_no ?? '') . ' / ' . ($l->orderPo->style->style_no ?? ''))->unique()->implode(', '),
            (int) $i->total_qty, (int) $i->total_cartons, $i->exportLc->currency->code ?? '', (float) $i->total_value,
            trim(($i->exp_no ?? '') . ($i->exp_date ? ' ' . $i->exp_date->format('d-M-y') : '')), trim(($i->bl_no ?? '') . ($i->bl_date ? ' ' . $i->bl_date->format('d-M-y') : '')),
            $i->shipMode->name ?? '',
        ]);

        return [
            'headers' => ['Date', 'Invoice', 'Buyer', 'LC / SC', 'PO / Style', 'Qty', 'Cartons', 'Cur', 'Value', 'EXP', 'B/L / AWB', 'Ship Mode'],
            'align' => [5 => 'right', 6 => 'right', 8 => 'right'],
            'rows' => $rows->all(),
            'totals' => [5 => $rows->sum(5), 6 => $rows->sum(6), 8 => round($rows->sum(8), 2)],
            'period' => $from || $to ? ($from?->format('d-M-Y') ?? '…') . ' – ' . ($to?->format('d-M-Y') ?? '…') : null,
        ];
    }
}
