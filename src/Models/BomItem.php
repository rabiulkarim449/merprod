<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    protected $table = 'msfl_bom_items';

    protected $fillable = [
        'bom_id', 'item_id', 'item_type', 'color_id', 'size_id', 'placement', 'consumption', 'uom_id',
        'wastage_percent', 'rate', 'supplier_id', 'remarks',
    ];

    protected $casts = [
        'consumption' => 'decimal:4',
        'wastage_percent' => 'decimal:2',
        'rate' => 'decimal:4',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Consumption per piece including wastage. */
    public function grossConsumption(): float
    {
        return (float) $this->consumption * (1 + (float) $this->wastage_percent / 100);
    }

    public function requiredQty(int $orderQty): float
    {
        return round($orderQty * $this->grossConsumption(), 4);
    }
}
