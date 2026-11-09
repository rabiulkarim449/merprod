<?php

namespace ME\MerchandisingSfl\Models\Production;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Size;

/**
 * One day's piece count at a stage for a PO: pieces taken in, then the QC
 * result — passed on, sent back for rework (stays in the stage until it
 * passes), or rejected — with defect rows saying which part / machine.
 * kind: production (stage screens) | qc / rework (QC and Rework screens, see ProductionFlow).
 */
class Entry extends Model
{
    use HasAudit;
    use SoftDeletes;

    protected $table = 'msfl_prod_entries';

    protected $fillable = ['stage', 'kind', 'entry_date', 'order_po_id', 'size_id', 'part_name', 'line_id', 'hour_slot', 'input_qty', 'pass_qty', 'rework_qty', 'reject_qty', 'remarks', 'created_by'];

    protected $casts = ['entry_date' => 'date'];

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class)->withTrashed();
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class)->withTrashed();
    }

    public function defects(): HasMany
    {
        return $this->hasMany(EntryDefect::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
