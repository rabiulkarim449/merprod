<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/**
 * Garment size, ordered by sort_order.
 * Read-only here: entered once in Inventory → Sizes.
 */
class Size extends Model
{
    use IsMaster;
    use SoftDeletes;

    protected $table = 'inv_sizes';

    protected $guarded = ['*'];

    protected $casts = ['is_active' => 'boolean'];

    /** Usual garment size order, used after sort_order (Inventory sizes often all have sort_order 0). */
    private const RANK = ['XXXS', 'XXS', '2XS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL', '6XL'];

    /** Sizes in display order: sort_order, then XS → S → M → L → XL → 2XL …, then numbers (28, 30 …), then by name. */
    public static function displaySort(Collection $sizes): Collection
    {
        return $sizes->sortBy(function (self $size) {
            $name = strtoupper(trim((string) $size->name));
            $rank = array_search(str_replace(['XXL', 'XXXL'], ['2XL', '3XL'], $name), self::RANK, true);
            $rank = $rank === false ? array_search($name, self::RANK, true) : $rank;

            return sprintf('%06d|%d|%010.2f|%s', (int) $size->sort_order, $rank === false ? (is_numeric($name) ? 1 : 2) : 0,
                $rank === false ? (is_numeric($name) ? (float) $name : 0) : $rank, $name);
        })->values();
    }
}
