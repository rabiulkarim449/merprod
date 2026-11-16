<?php

namespace ME\MerchandisingSfl\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use ME\MerchandisingSfl\Support\BuyerVisibility;

/**
 * Report pages only: rows of a buyer type hidden from the logged-in role filter
 * themselves out (see BuyerVisibility). Does nothing on any other page.
 *
 * The model says how it reaches its buyer with:
 *   public const BUYER_VIA = ['buyer' | 'order' | 'po' | 'style' | 'plan' | 'self', '<column>'];
 */
trait BuyerScoped
{
    public static function bootBuyerScoped(): void
    {
        static::addGlobalScope('buyer_visibility', function (Builder $query) {
            if (! BuyerVisibility::active()) {
                return;
            }
            [$via, $column] = static::BUYER_VIA;
            $column = $query->getModel()->qualifyColumn($column);
            if ($via === 'self') {
                $query->whereNotIn($column, BuyerVisibility::activeIds());
            } else {
                BuyerVisibility::{$via}($query, $column);
            }
        });
    }
}
