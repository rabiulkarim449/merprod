<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Pre-Production Readiness — one row per PO around its PCD, one column per approval gate. */
class PreProductionReadiness extends OpsReport
{
    private const GATE = ['Approved', 'Pending', 'In Progress', 'Not Applicable'];

    /** gate key => label; auto = the system works it out (a typed choice wins). */
    private const GATES = [
        'tech_pack' => ['Tech Pack', true], 'pp_sample' => ['PP Sample', true], 'size_set' => ['Size Set', true],
        'fabric' => ['Fabric', true], 'trims' => ['Trims', true], 'pattern' => ['Pattern', false], 'marker' => ['Marker', false],
        'wash' => ['Wash Approval', true], 'testing' => ['Testing', false], 'pp_meeting' => ['PP Meeting', true], 'other' => ['Other Approval', false],
    ];

    public function key(): string
    {
        return 'pre-production';
    }

    public function title(): string
    {
        return 'Pre-Production Readiness Report';
    }

    public function department(): string
    {
        return 'Merchandising / Sample / QA';
    }

    public function guidance(): string
    {
        return 'Select Approved, Pending, In Progress or Not Applicable for every approval gate. Readiness and risk calculate automatically. Tech Pack, PP Sample, Size Set, Fabric, Trims, Wash and PP Meeting fill in from the Tech Pack, Samples and T&A (pick a value to override); Pattern, Marker, Testing and Other are entered here (blank counts as Pending). Date = PCD. Ready = every applicable gate Approved; Blocked = PCD reached and not ready; At Risk = PCD within 7 days.';
    }

    public function filters(): array
    {
        return ['buyer', 'range'];
    }

    public function columns(): array
    {
        $cols = [
            self::col('date', 'Date', 'auto', 'date'), self::col('buyer', 'Buyer'), self::col('style_no', 'Style No.'), self::col('po_no', 'PO No.'), self::col('product', 'Product'),
            self::col('pp_meeting_plan', 'PP Meeting Plan', 'auto_input', 'date'),
        ];
        foreach (self::GATES as $k => [$label, $auto]) {
            $cols[] = self::col($k, $label, $auto ? 'auto_input' : 'input', 'select', ['options' => self::GATE, 'status' => true]);
        }

        return array_merge($cols, [
            self::col('readiness', 'Readiness %', 'formula', 'pct'),
            self::col('overall', 'Overall Status', 'formula', 'text', ['status' => true]),
            self::col('owner', 'Owner', 'input'),
            self::col('missing', 'Missing Item / Action', 'auto_input'),
        ]);
    }

    protected function rows(Request $r, Carbon $date, array $settings): array
    {
        $from = $r->filled('from') ? Carbon::parse($r->from) : $date->copy()->subDays(15);
        $to = $r->filled('to') ? Carbon::parse($r->to) : $date->copy()->addDays(45);
        $pos = $this->pos($r)->whereBetween('pcd_date', [$from->toDateString(), $to->toDateString()])->orderBy('pcd_date')->get();

        $samples = DB::table('msfl_samples as s')->join('msfl_sample_types as t', 't.id', '=', 's.sample_type_id')->whereNull('s.deleted_at')
            ->whereIn('s.style_id', $pos->pluck('style_id')->unique())->whereIn('t.code', ['PP', 'SIZESET'])
            ->orderBy('s.id')->get(['s.style_id', 't.code', 's.status'])->groupBy(fn ($x) => $x->style_id . '|' . $x->code)->map->last();
        $tasks = DB::table('msfl_tna_tasks as k')->join('msfl_tna_plans as p', 'p.id', '=', 'k.tna_plan_id')->whereNull('p.deleted_at')->where('p.status', '!=', 'cancelled')
            ->whereIn('p.order_id', $pos->pluck('order_id')->unique())
            ->whereIn('k.task_code', ['PP_MEETING', 'FABRIC_INHOUSE', 'TRIMS_INHOUSE', 'WASH_STANDARD', 'SIZE_SET', 'PP_APPROVAL'])
            ->get(['p.order_id', 'p.style_id', 'k.task_code', 'k.plan_date', 'k.revised_date', 'k.actual_date', 'k.is_na'])
            ->keyBy(fn ($t) => $t->order_id . '|' . $t->style_id . '|' . $t->task_code);
        $techPack = DB::table('msfl_styles')->whereIn('id', $pos->pluck('style_id'))->pluck('tech_pack_file', 'id');

        return $pos->map(function ($po) use ($samples, $tasks, $techPack) {
            $task = fn ($code) => $tasks[$po->order_id . '|' . $po->style_id . '|' . $code] ?? null;
            // A T&A task: done → Approved, marked N/A → Not Applicable, else Pending.
            $byTask = fn ($code) => ($t = $task($code)) ? ($t->actual_date ? 'Approved' : ($t->is_na ? 'Not Applicable' : 'Pending')) : 'Pending';
            $bySample = function (string $type, string $code) use ($samples, $po, $task) {
                if ($task($code)?->actual_date) {
                    return 'Approved';
                }
                $s = $samples[$po->style_id . '|' . $type] ?? null;

                return ! $s ? ($task($code)?->is_na ? 'Not Applicable' : 'Pending') : match ($s->status) {
                    'approved' => 'Approved', 'rejected' => 'Pending', default => 'In Progress',
                };
            };
            $pp = $task('PP_MEETING');

            return ['_key' => 'po:' . $po->id, '_label' => 'PO ' . $po->po_no] + $this->poCells($po) + [
                'date' => $po->pcd_date?->toDateString(),
                'pp_meeting_plan' => $pp ? ($pp->revised_date ?? $pp->plan_date) : null,
                'tech_pack' => ! empty($techPack[$po->style_id]) ? 'Approved' : 'Pending',
                'pp_sample' => $bySample('PP', 'PP_APPROVAL'),
                'size_set' => $bySample('SIZESET', 'SIZE_SET'),
                'fabric' => $byTask('FABRIC_INHOUSE'),
                'trims' => $byTask('TRIMS_INHOUSE'),
                'wash' => $po->needs_washing ? $byTask('WASH_STANDARD') : 'Not Applicable',
                'pp_meeting' => $byTask('PP_MEETING'),
            ];
        })->all();
    }

    protected function compute(array $rows, Carbon $date, array $settings): array
    {
        foreach ($rows as &$x) {
            $applicable = 0;
            $approved = 0;
            $missing = [];
            foreach (self::GATES as $k => [$label]) {
                $v = $x[$k] ?: 'Pending';
                $x[$k] = $v;
                if ($v === 'Not Applicable') {
                    continue;
                }
                $applicable++;
                if ($v === 'Approved') {
                    $approved++;
                } else {
                    $missing[] = $label . ($v === 'In Progress' ? ' (in progress)' : '');
                }
            }
            $x['readiness'] = $applicable ? $approved / $applicable * 100 : 100;
            $days = $x['date'] ? (int) $date->diffInDays(Carbon::parse($x['date']), false) : null;
            $x['overall'] = match (true) {
                $x['readiness'] >= 100 => 'Ready',
                $days !== null && $days <= 0 => 'Blocked',
                $days !== null && $days <= 7 => 'At Risk',
                default => 'In Progress',
            };
            if (($x['_manual']['missing'] ?? null) === null) {
                $x['missing'] = $missing ? 'Pending: ' . implode(', ', $missing) : '';
            }
        }

        return $rows;
    }

    public function kpis(array $rows): array
    {
        $c = collect($rows);

        return [
            ['Styles / POs', self::fmt($c->count(), 'int')],
            ['Ready', self::fmt($c->where('overall', 'Ready')->count(), 'int')],
            ['At Risk', self::fmt($c->where('overall', 'At Risk')->count(), 'int')],
            ['Blocked', self::fmt($c->where('overall', 'Blocked')->count(), 'int')],
            ['Avg. Readiness', self::fmt($c->avg('readiness') ?? 0, 'pct')],
        ];
    }
}
