<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Floor / sewing line — read-only here: entered once in HR → Floor Lines. */
class FloorLine extends Model
{
    protected $table = 'hr_floor_lines';

    protected $guarded = ['*'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 'active');
    }

    /** "Floor 1 · Line 2" */
    public function getLabelAttribute(): string
    {
        return trim(($this->floor_name ? $this->floor_name . ' · ' : '') . $this->line_name);
    }
}
