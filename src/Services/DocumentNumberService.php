<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * "<PREFIX>-<YYYY>-<0001>" numbers (prefixes in config number_prefixes);
 * daily() = "<PREFIX><YYMMDD>-<001>" for commercial invoices.
 * Call inside the same DB transaction that inserts the row — the latest
 * number of the year is read with a row lock so two saves can't collide.
 */
class DocumentNumberService
{
    /** @param class-string<Model> $model */
    public function next(string $type, string $model, string $column): string
    {
        $prefix = config("merchandising-sfl.number_prefixes.$type", strtoupper($type)) . '-' . now()->format('Y') . '-';

        $query = $model::query();
        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        $last = $query->where($column, 'like', $prefix . '%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * "<PREFIX><YYMMDD>-<001>" — the counter restarts every day (date = the
     * document's own date), e.g. IN261017-001. Same locking rule as next().
     *
     * @param class-string<Model> $model
     */
    public function daily(string $type, string $model, string $column, $date): string
    {
        $prefix = config("merchandising-sfl.number_prefixes.$type", strtoupper($type)) . \Illuminate\Support\Carbon::parse($date ?? now())->format('ymd') . '-';

        $query = $model::query();
        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        $last = $query->where($column, 'like', $prefix . '%')
            ->orderByDesc($column)
            ->lockForUpdate()
            ->value($column);

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    /** Packing list no of a commercial invoice: same date + counter, PL prefix (IN261017-001 → PL261017-001). */
    public static function packingListNo(string $invoiceNo): string
    {
        $in = config('merchandising-sfl.number_prefixes.com_invoice', 'IN');
        $pl = config('merchandising-sfl.number_prefixes.packing_list', 'PL');

        return str_starts_with($invoiceNo, $in) ? $pl . substr($invoiceNo, strlen($in)) : $invoiceNo;
    }
}
