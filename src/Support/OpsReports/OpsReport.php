<?php

namespace ME\MerchandisingSfl\Support\OpsReports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\ReportInput;
use ME\MerchandisingSfl\Services\ProductionFlow;

/**
 * One operations report laid out like its Excel template: header, KPI tiles, guidance and a
 * table whose columns are
 *   auto       — straight from production / inventory / commercial data (read-only),
 *   input      — typed by the user (the template's pale-yellow cells), stored in msfl_report_inputs,
 *   auto_input — from the data when the system has it, the typed value wins when entered,
 *   formula    — worked out from the row (the template's pale-blue cells).
 * Every typed cell is validated by its column's type and limit before it is saved.
 */
abstract class OpsReport
{
    /** Shared figures every report can read: field => [label, default]. */
    public const SETTINGS = [
        'bdt_usd_rate' => ['BDT/USD Rate', 120],
        'target_margin' => ['Target Margin %', 15],
        'operator_rate' => ['Operator Rate (BDT/hr)', null],
        'helper_rate' => ['Helper Rate (BDT/hr)', null],
        'ot_rate' => ['OT Rate (BDT/hr per person)', null],
        'oh_per_line_day' => ['Overhead per Line per Day (BDT)', null],
        'default_cpm' => ['Default CPM (USD)', null],
    ];

    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function department(): string;

    abstract public function guidance(): string;

    /** Column definitions — see col(). */
    abstract public function columns(): array;

    /** The rows with their auto values: each ['_key' => unique id, '_label' => how errors name it, col => value …]. */
    abstract protected function rows(Request $r, Carbon $date, array $settings): array;

    /** Fill the formula columns (all rows at once — some need the other rows). */
    abstract protected function compute(array $rows, Carbon $date, array $settings): array;

    /** KPI tiles: [[label, value], …] (value already formatted). */
    abstract public function kpis(array $rows): array;

    /** Filters shown above the table: 'date' (reporting date) is always there; 'range' (from / to) and 'buyer' optional. */
    public function filters(): array
    {
        return ['buyer'];
    }

    /** Which settings this report shows in its header (keys of SETTINGS). */
    public function settingsShown(): array
    {
        return [];
    }

    /** Signature captions under the printed report. */
    public function signatures(): array
    {
        return [];
    }

    public function landscape(): bool
    {
        return true;
    }

    // ── Building ─────────────────────────────────────────────────────────────

    public function build(Request $r): array
    {
        $date = $r->filled('date') ? Carbon::parse($r->date)->startOfDay() : today();
        $settings = $this->settings();
        $rows = $this->rows($r, $date, $settings);
        $stored = ReportInput::forRows($this->key(), array_column($rows, '_key'));
        $cols = $this->columns();

        foreach ($rows as &$row) {
            $row['_auto'] = [];
            $row['_manual'] = [];
            foreach ($cols as $c) {
                if (! in_array($c['kind'], ['input', 'auto_input'], true)) {
                    continue;
                }
                $manual = $stored[$row['_key']][$c['key']] ?? null;
                $row['_auto'][$c['key']] = $row[$c['key']] ?? null;
                $row['_manual'][$c['key']] = $manual;
                $row[$c['key']] = $manual !== null ? self::cast($manual, $c['type']) : ($c['kind'] === 'auto_input' ? ($row[$c['key']] ?? null) : null);
            }
        }
        unset($row);
        $rows = $this->compute($rows, $date, $settings);

        return ['date' => $date, 'rows' => $rows, 'kpis' => $this->kpis($rows), 'settings' => $settings, 'columns' => $cols];
    }

    public function settings(): array
    {
        $stored = ReportInput::settings();
        $out = [];
        foreach (self::SETTINGS as $k => [, $default]) {
            $out[$k] = isset($stored[$k]) && $stored[$k] !== '' ? (float) $stored[$k] : $default;
        }

        return $out;
    }

    /**
     * Checks posted cells against the freshly built report: the row must still be in it, the column
     * must be a typed one, and the value must fit the column. Returns [errors, [rowKey => [field => value|null]]].
     */
    public function check(array $posted, array $built): array
    {
        $rows = collect($built['rows'])->keyBy('_key');
        $cols = collect($this->columns())->keyBy('key');
        $errors = [];
        $clean = [];

        foreach ($posted as $rowKey => $fields) {
            $row = $rows->get($rowKey);
            if (! $row || ! is_array($fields)) {
                $errors[] = "A row ({$rowKey}) is no longer in this report — reload and try again.";
                continue;
            }
            foreach ($fields as $field => $value) {
                $c = $cols->get($field);
                if (! $c || ! in_array($c['kind'], ['input', 'auto_input'], true)) {
                    continue;
                }
                $value = is_string($value) ? trim($value) : $value;
                if ($value === null || $value === '') {
                    $clean[$rowKey][$field] = null;
                    continue;
                }
                $msg = $this->checkValue((string) $value, $c, $row);
                if ($msg) {
                    $errors[] = ($row['_label'] ?? $rowKey) . ' — ' . $c['label'] . ': ' . $msg;
                    continue;
                }
                $clean[$rowKey][$field] = (string) $value;
            }
        }

        return [$errors, $clean];
    }

    private function checkValue(string $v, array $c, array $row): ?string
    {
        $max = isset($c['max']) ? (is_callable($c['max']) ? ($c['max'])($row) : $c['max']) : null;
        $min = $c['min'] ?? 0;

        switch ($c['type']) {
            case 'int':
                if (! preg_match('/^-?\d+$/', $v)) {
                    return 'enter a whole number.';
                }
                break;
            case 'num':
            case 'money':
                if (! is_numeric($v)) {
                    return 'enter a number.';
                }
                break;
            case 'pct':
                if (! is_numeric($v)) {
                    return 'enter a number (0–100).';
                }
                $max ??= 100;
                break;
            case 'date':
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || ! strtotime($v)) {
                    return 'enter a valid date.';
                }

                return null;
            case 'select':
                return in_array($v, $c['options'], true) ? null : 'pick one of: ' . implode(', ', $c['options']) . '.';
            default:
                return mb_strlen($v) > 255 ? 'keep it under 255 characters.' : null;
        }
        if ((float) $v < $min) {
            return "can't be less than {$min}.";
        }
        if ($max !== null && (float) $v > $max + 0.0001) {
            return "can't be more than " . self::fmt($max, $c['type'] === 'pct' ? 'num' : $c['type']) . ($c['max_note'] ?? '') . '.';
        }

        return null;
    }

    // ── Column helpers ───────────────────────────────────────────────────────

    /** $type: text | int | num | money | pct | date | select. Extra: options (select), max (number or fn(row)), min, max_note, status (colour by value). */
    protected static function col(string $key, string $label, string $kind = 'auto', string $type = 'text', array $extra = []): array
    {
        return ['key' => $key, 'label' => $label, 'kind' => $kind, 'type' => $type] + $extra;
    }

    public static function cast($v, string $type)
    {
        return match ($type) {
            'int' => (int) $v,
            'num', 'money', 'pct' => (float) $v,
            default => (string) $v,
        };
    }

    public static function fmt($v, string $type): string
    {
        if ($v === null || $v === '') {
            return '';
        }

        return match ($type) {
            'int' => number_format((int) round((float) $v)),
            'num' => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'),
            'money' => number_format((float) $v, 2),
            'pct' => number_format((float) $v, 1) . '%',
            'date' => ($d = strtotime((string) $v)) ? date('d-M-y', $d) : (string) $v,
            default => (string) $v,
        };
    }

    /** Safe a ÷ b (null when b is 0 / missing). */
    protected static function div($a, $b): ?float
    {
        return $b !== null && (float) $b != 0.0 && $a !== null ? (float) $a / (float) $b : null;
    }

    protected static function pct($a, $b): ?float
    {
        $d = self::div($a, $b);

        return $d === null ? null : $d * 100;
    }

    // ── Shared data ──────────────────────────────────────────────────────────

    /** Confirmed-order POs, with buyer / style / product type / color. */
    protected function pos(Request $r)
    {
        return OrderPo::query()->with(['order.buyer', 'style.productType', 'color'])
            ->whereHas('order', fn ($q) => $q->where('status', 'confirmed')->when($r->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $r->buyer_id)));
    }

    /** Common row identity for a PO. */
    protected function poCells(OrderPo $po): array
    {
        return [
            'buyer' => $po->order->buyer->name ?? '',
            'style_no' => $po->style->style_no ?? '',
            'po_no' => $po->po_no,
            'product' => $po->style->productType->name ?? ($po->style->name ?? ''),
            'colour' => $po->color->name ?? '',
            'order_qty' => (int) $po->po_qty,
        ];
    }

    /** po_id => [stage => net pass] (QC / rework adjusted, as the production screens count it), optionally up to / on a date. */
    protected function netPass($poIds, ?string $upTo = null, ?string $on = null): array
    {
        $out = [];
        DB::table('msfl_prod_entries')->whereNull('deleted_at')->whereIn('order_po_id', $poIds)
            ->when($upTo, fn ($q) => $q->where('entry_date', '<=', $upTo))->when($on, fn ($q) => $q->where('entry_date', $on))
            ->selectRaw('order_po_id, stage, SUM(' . ProductionFlow::NET_PASS_SQL . ') p')->groupBy('order_po_id', 'stage')->get()
            ->each(function ($x) use (&$out) {
                $out[$x->order_po_id][$x->stage] = (int) $x->p;
            });

        return $out;
    }

    /** CM per piece (USD): style Confirm CM, else the approved cost sheet's CM, else Target CM — all per dozen ÷ 12. */
    protected function cmPerPiece($styleIds): array
    {
        $styles = DB::table('msfl_styles')->whereIn('id', $styleIds)->get(['id', 'confirm_cm', 'target_cm'])->keyBy('id');
        $sheets = DB::table('msfl_cost_sheets')->whereNull('deleted_at')->where('status', 'approved')->whereIn('style_id', $styleIds)
            ->orderBy('id')->get(['style_id', 'cm_cost', 'cm_minute_rate'])->keyBy('style_id');
        $out = [];
        foreach ($styleIds as $id) {
            $s = $styles[$id] ?? null;
            $dz = (float) ($s->confirm_cm ?? 0) ?: (float) ($sheets[$id]->cm_cost ?? 0) ?: (float) ($s->target_cm ?? 0);
            $out[$id] = ['cm' => $dz > 0 ? round($dz / 12, 4) : null, 'cpm' => (float) ($sheets[$id]->cm_minute_rate ?? 0) ?: null];
        }

        return $out;
    }
}
