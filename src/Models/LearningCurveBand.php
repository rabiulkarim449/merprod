<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/**
 * Day-wise Learning Curve: an SMV band's expected efficiency % on each
 * production day (a new style never runs at 100% from day 1 — workers
 * ramp up). 'production' = the shared floor target; 'shipment' = Planning
 * Manager's own, more conservative estimate (see Support\LearningCurve).
 */
class LearningCurveBand extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_learning_curve_bands';

    protected $fillable = [
        'curve_type', 'label', 'smv_from', 'smv_to', 'reference_smv', 'day_percents', 'sequence', 'is_active', 'created_by',
    ];

    protected $casts = [
        'day_percents' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('curve_type', $type);
    }

    /** The band whose smv_from/smv_to bracket the given SMV, or null if none covers it. */
    public static function forSmv(string $curveType, float $smv): ?self
    {
        return static::active()->type($curveType)
            ->where(fn ($q) => $q->whereNull('smv_from')->orWhere('smv_from', '<=', $smv))
            ->where(fn ($q) => $q->whereNull('smv_to')->orWhere('smv_to', '>=', $smv))
            ->orderBy('sequence')
            ->first();
    }
}
