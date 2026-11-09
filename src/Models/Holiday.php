<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Holiday (a date range) — read-only here: entered once in HR → Holidays.
 * WorkingCalendar skips every day from from_date to to_date.
 */
class Holiday extends Model
{
    protected $table = 'hr_holidays';

    protected $guarded = ['*'];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 1);
    }
}
