<?php

namespace ME\MerchandisingSfl\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Hide Export Buyer" / "Hide Local Buyer" — two ticks on the Buyers row of a role.
 *
 * A role with exactly ONE of them ticked does not see that type of buyer on report
 * and dashboard pages: its orders, and everything entered against those orders, drop
 * out of the rows and the totals. Entry screens and lists are never touched.
 *
 * Both ticked means the same as none ticked (everything shows): hiding both types
 * would only leave empty reports, and it keeps a "tick everything" role (Super Admin)
 * from blinding itself.
 *
 * The rule is switched on per request by the HideBuyers middleware; models carrying
 * the BuyerScoped trait then filter themselves. Raw DB::table() queries call the
 * buyer() / order() / po() helpers below.
 */
class BuyerVisibility
{
    public const PERMISSION = 'msfl_buyer';

    /** Role tick => the buyer type it hides. */
    public const HIDE = ['hide_export' => 'export', 'hide_local' => 'local'];

    private static bool $enabled = false;

    /** @var array<int|string, int[]> hidden buyer ids, per user */
    private static array $ids = [];

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /** The buyer type the logged-in user must not see in reports, or null. */
    public static function hiddenType(): ?string
    {
        $user = auth()->user();
        if (! $user || ! method_exists($user, 'hasPermission')) {
            return null;
        }
        $hidden = [];
        foreach (self::HIDE as $action => $type) {
            if ($user->hasPermission(self::PERMISSION . '.' . $action)) {
                $hidden[] = $type;
            }
        }

        return count($hidden) === 1 ? $hidden[0] : null;
    }

    /** Buyer ids hidden from the logged-in user, whatever page this is. Used by other packages too. */
    public static function hiddenBuyerIds(): array
    {
        $type = self::hiddenType();
        if ($type === null) {
            return [];
        }
        $key = auth()->id() . '|' . $type;
        if (! isset(self::$ids[$key])) {
            self::$ids[$key] = Schema::hasColumn('msfl_buyers', 'buyer_type')
                ? DB::table('msfl_buyers')->where('buyer_type', $type)->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];
        }

        return self::$ids[$key];
    }

    /** Hidden buyer ids on a page where the rule is switched on; [] everywhere else. */
    public static function activeIds(): array
    {
        return self::$enabled ? self::hiddenBuyerIds() : [];
    }

    public static function active(): bool
    {
        return self::activeIds() !== [];
    }

    /** Rows whose buyer column is hidden drop out; rows without a buyer stay. */
    public static function buyer($query, string $column = 'buyer_id')
    {
        $ids = self::activeIds();

        return $ids === [] ? $query : $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column, $ids));
    }

    /** Same, for a column holding an msfl_orders id. */
    public static function order($query, string $column = 'order_id')
    {
        $ids = self::activeIds();

        return $ids === [] ? $query : $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column, self::hiddenOrders($ids)));
    }

    /** Same, for a column holding an msfl_order_pos id. */
    public static function po($query, string $column = 'order_po_id')
    {
        $ids = self::activeIds();

        return $ids === [] ? $query : $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column,
            DB::table('msfl_order_pos')->whereIn('order_id', self::hiddenOrders($ids))->select('id')));
    }

    /** Same, for a column holding an msfl_styles id. */
    public static function style($query, string $column = 'style_id')
    {
        $ids = self::activeIds();

        return $ids === [] ? $query : $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column,
            DB::table('msfl_styles')->whereIn('buyer_id', $ids)->select('id')));
    }

    /** Same, for a column holding an msfl_tna_plans id. */
    public static function plan($query, string $column = 'tna_plan_id')
    {
        $ids = self::activeIds();

        return $ids === [] ? $query : $query->where(fn ($q) => $q->whereNull($column)->orWhereNotIn($column,
            DB::table('msfl_tna_plans')->whereIn('order_id', self::hiddenOrders($ids))->select('id')));
    }

    private static function hiddenOrders(array $buyerIds)
    {
        return DB::table('msfl_orders')->whereIn('buyer_id', $buyerIds)->select('id');
    }
}
