<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Department — read-only here: entered once in HR. */
class Department extends Model
{
    protected $table = 'hr_departments';

    protected $guarded = ['*'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 'active');
    }
}
