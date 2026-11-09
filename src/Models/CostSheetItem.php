<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostSheetItem extends Model
{
    protected $table = 'msfl_cost_sheet_items';

    protected $fillable = ['cost_sheet_id', 'group', 'item_id', 'description', 'supplier_name', 'uom_id', 'consumption', 'rate', 'amount', 'remarks'];

    protected $casts = [
        'consumption' => 'decimal:4',
        'rate' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (CostSheetItem $line) {
            $line->amount = round((float) $line->consumption * (float) $line->rate, 4);
        });
    }

    public function costSheet(): BelongsTo
    {
        return $this->belongsTo(CostSheet::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** What the line is: its description, else the library item's name. */
    public function label(): string
    {
        return (string) ($this->description ?: ($this->item->name ?? ''));
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
