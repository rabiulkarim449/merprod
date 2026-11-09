<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * One sewing line's day on one PO: target (pcs for the day), working hours,
 * SMV, operators and helpers. Saved from the hourly output form; drives the
 * Sewing board's work minutes, produced minutes and efficiency.
 */
class SewingPlan extends Model
{
    protected $table = 'msfl_prod_sewing_plans';

    protected $fillable = ['plan_date', 'line_id', 'order_po_id', 'target', 'working_hours', 'smv', 'operators', 'helpers', 'remarks', 'created_by'];

    protected $casts = ['plan_date' => 'date', 'working_hours' => 'decimal:1', 'smv' => 'decimal:3'];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class)->withTrashed();
    }

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }

    public function manpower(): int
    {
        return (int) $this->operators + (int) $this->helpers;
    }
}
