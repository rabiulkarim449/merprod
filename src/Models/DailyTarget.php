<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** Day targets (Planning): cutting pcs, packing (poly) pcs, FOB value per sewing line. */
class DailyTarget extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_daily_targets';

    protected $fillable = ['target_date', 'cutting_target', 'packing_target', 'line_required_value', 'remarks', 'is_active', 'created_by'];

    protected $casts = ['target_date' => 'date', 'line_required_value' => 'decimal:2', 'is_active' => 'boolean'];
}
