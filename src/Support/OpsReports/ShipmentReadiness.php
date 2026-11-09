<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Shipment Readiness & Risk — one row per PO shipping in the period. */
class ShipmentReadiness extends OpsReport
{
    private const DOCS = ['packing_list', 'invoice', 'exp', 'ud_contract', 'bl_awb', 'freight_booking', 'customs_status'];

    public function key(): string
    {
        return 'shipment-readiness';
    }

    public function title(): string
    {
        return 'Shipment Readiness & Risk Report';
    }

    public function department(): string
    {
        return 'Merchandising / Commercial';
    }

    public function guidance(): string
    {
        return 'Update production, inspection and export-document status for every PO. Overall shipment risk calculates automatically. Finished / Packed come from Production; Packing List, Invoice, EXP, UD / Contract and BL / AWB come from Final Packing List and Commercial when entered there (a typed value overrides). Rows: POs whose ex-factory / shipment date falls in the period (default 15 days back to 45 ahead).';
    }

    public function filters(): array
    {
        return ['buyer', 'range'];
    }

    public function columns(): array
    {
        $risk = ['status' => true];

        return [
            self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'), self::col('product', 'Product'),
            self::col('order_qty', 'Order Qty', 'auto', 'int'),
            self::col('ex_factory', 'Ex-Factory Date', 'auto_input', 'date'),
            self::col('finished_qty', 'Finished Qty', 'auto', 'int'),
            self::col('packed_qty', 'Packed Qty', 'auto', 'int'),
            self::col('prod_ready', 'Production Ready %', 'formula', 'pct'),
            self::col('inspection_booking', 'Inspection Booking', 'input', 'date'),
            self::col('inspection_status', 'Inspection Status', 'input', 'select', ['options' => ['Not Booked', 'Booked', 'Passed', 'Failed', 'Re-inspection']]),
            self::col('aql_result', 'AQL Result', 'input', 'select', ['options' => ['Pending', 'Pass', 'Fail']]),
            self::col('carton_status', 'Carton Status', 'input', 'select', ['options' => ['Not Started', 'In Progress', 'Completed']]),
            self::col('packing_list', 'Packing List', 'auto_input'),
            self::col('invoice', 'Invoice', 'auto_input'),
            self::col('exp', 'EXP', 'auto_input'),
            self::col('ud_contract', 'UD / Contract', 'auto_input'),
            self::col('bl_awb', 'BL / AWB', 'auto_input'),
            self::col('freight_booking', 'Freight Booking', 'input', 'select', ['options' => ['Pending', 'Booked', 'Not Required']]),
            self::col('customs_status', 'Customs Status', 'input', 'select', ['options' => ['Pending', 'Submitted', 'Cleared']]),
            self::col('docs_ready', 'Documents Ready %', 'formula', 'pct'),
            self::col('overall_risk', 'Overall Risk', 'formula', 'text', $risk),
            self::col('required_action', 'Required Action', 'input'),
            self::col('owner', 'Owner', 'input'),
            self::col('remarks', 'Remarks', 'input'),
        ];
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from) : $date->copy()->subDays(15);
        $to = $r->filled('to') ? Carbon::parse($r->to) : $date->copy()->addDays(45);
        $plans = DB::table('msfl_tna_plans')->whereNull('deleted_at')->where('status', '!=', 'cancelled')->whereNotNull('ex_factory_date')
            ->get(['order_id', 'style_id', 'shipment_date', 'ex_factory_date'])->keyBy(fn ($p) => $p->order_id . '|' . $p->style_id);
        // One T&A covers an order + style; each PO keeps the T&A's shipment → ex-factory gap on its own shipment date.
        $exFactory = function ($po) use ($plans) {
            $plan = $plans[$po->order_id . '|' . $po->style_id] ?? null;
            if (! $po->shipment_date) {
                return $plan->ex_factory_date ?? null;
            }
            if (! $plan || ! $plan->shipment_date) {
                return $po->shipment_date->toDateString();
            }
            $gap = Carbon::parse($plan->ex_factory_date)->diffInDays(Carbon::parse($plan->shipment_date), false);

            return $po->shipment_date->copy()->subDays(max(0, (int) $gap))->toDateString();
        };

        $pos = $this->pos($r)->orderBy('shipment_date')->get()->filter(function ($po) use ($exFactory, $from, $to) {
            $ex = $exFactory($po);

            return $ex && $ex >= $from->toDateString() && $ex <= $to->toDateString();
        })->values();
        $ids = $pos->pluck('id');
        $pass = $this->netPass($ids);

        $fpl = DB::table('msfl_prod_final_packing_lists')->whereNull('deleted_at')->whereIn('order_id', $pos->pluck('order_id'))
            ->get(['order_id', 'po_no', 'fpl_no', 'status'])->keyBy(fn ($f) => $f->order_id . '|' . $f->po_no);
        $inv = DB::table('msfl_com_invoice_lines as l')->join('msfl_com_invoices as i', 'i.id', '=', 'l.invoice_id')->whereNull('i.deleted_at')
            ->whereIn('l.order_po_id', $ids)->get(['l.order_po_id', 'i.invoice_no', 'i.exp_no', 'i.bl_no'])->groupBy('order_po_id');
        $lc = DB::table('msfl_com_export_lc_pos as p')->join('msfl_com_export_lcs as c', 'c.id', '=', 'p.export_lc_id')->whereNull('c.deleted_at')
            ->whereIn('p.order_po_id', $ids)->get(['p.order_po_id', 'c.lc_no', 'c.buyer_lc_no'])->groupBy('order_po_id');
        $list = fn ($rows, $f) => collect($rows)->pluck($f)->filter()->unique()->implode(', ') ?: null;

        return $pos->map(function ($po) use ($exFactory, $pass, $fpl, $inv, $lc, $list) {
            $f = $fpl[$po->order_id . '|' . $po->po_no] ?? null;

            return ['_key' => 'po:' . $po->id, '_label' => 'PO ' . $po->po_no] + $this->poCells($po) + [
                'ex_factory' => $exFactory($po),
                'finished_qty' => $pass[$po->id]['finishing'] ?? 0,
                'packed_qty' => $pass[$po->id]['packing'] ?? 0,
                'packing_list' => $f ? ($f->status === 'approved' ? 'Approved ' : 'Draft ') . $f->fpl_no : null,
                'invoice' => $list($inv[$po->id] ?? [], 'invoice_no'),
                'exp' => $list($inv[$po->id] ?? [], 'exp_no'),
                'ud_contract' => collect($lc[$po->id] ?? [])->map(fn ($c) => trim($c->lc_no . ($c->buyer_lc_no ? ' / ' . $c->buyer_lc_no : '')))->unique()->implode(', ') ?: null,
                'bl_awb' => $list($inv[$po->id] ?? [], 'bl_no'),
            ];
        })->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $x['prod_ready'] = self::pct($x['packed_qty'], $x['order_qty']);
            $done = 0;
            foreach (self::DOCS as $d) {
                $v = (string) ($x[$d] ?? '');
                $done += match ($d) {
                    'packing_list' => $v !== '' && ! str_starts_with($v, 'Draft') ? 1 : 0,
                    'freight_booking' => in_array($v, ['Booked', 'Not Required'], true) ? 1 : 0,
                    'customs_status' => $v === 'Cleared' ? 1 : 0,
                    default => $v !== '' ? 1 : 0,
                };
            }
            $x['docs_ready'] = $done / count(self::DOCS) * 100;
            $days = $x['ex_factory'] ? (int) $date->diffInDays(Carbon::parse($x['ex_factory']), false) : null;
            $ready = ($x['prod_ready'] ?? 0) >= 100 && ($x['aql_result'] ?? '') === 'Pass' && $x['docs_ready'] >= 100;
            $x['overall_risk'] = match (true) {
                $ready => 'Ready',
                ($x['aql_result'] ?? '') === 'Fail' || ($x['inspection_status'] ?? '') === 'Failed' || ($days !== null && $days <= 3) => 'Critical',
                $days !== null && $days <= 7 => 'High',
                $days !== null && $days <= 14 => 'Medium',
                default => 'Low',
            };
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);

        return [
            ['Shipment POs', self::fmt($c->count(), 'int')],
            ['Order Qty', self::fmt($c->sum('order_qty'), 'int')],
            ['Packed Qty', self::fmt($c->sum('packed_qty'), 'int')],
            ['Ready POs', self::fmt($c->where('overall_risk', 'Ready')->count(), 'int')],
            ['Critical / High', self::fmt($c->whereIn('overall_risk', ['Critical', 'High'])->count(), 'int')],
        ];
    }
}
