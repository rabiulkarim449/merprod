<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;

/** One hand-entered cell of an operations report (see Support\OpsReports). */
class ReportInput extends Model
{
    public const SETTINGS = '_settings';

    protected $table = 'msfl_report_inputs';

    protected $fillable = ['report', 'row_key', 'field', 'value', 'updated_by'];

    /** row_key => [field => value] for a report's rows. */
    public static function forRows(string $report, array $rowKeys): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($rowKeys)), 500) as $chunk) {
            foreach (static::query()->where('report', $report)->whereIn('row_key', $chunk)->get(['row_key', 'field', 'value']) as $i) {
                $out[$i->row_key][$i->field] = $i->value;
            }
        }

        return $out;
    }

    /** Shared figures (exchange rate, labour rates …): field => value. */
    public static function settings(): array
    {
        return static::query()->where('report', self::SETTINGS)->pluck('value', 'field')->all();
    }

    /** Save (or clear, when $value is null / '') one cell. */
    public static function put(string $report, string $rowKey, string $field, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            static::query()->where(['report' => $report, 'row_key' => $rowKey, 'field' => $field])->delete();

            return;
        }
        static::query()->updateOrCreate(
            ['report' => $report, 'row_key' => $rowKey, 'field' => $field],
            ['value' => trim($value), 'updated_by' => auth()->id()],
        );
    }
}
