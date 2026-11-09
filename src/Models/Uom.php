<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/**
 * Unit of measure (Inventory unit).
 * Read-only here: entered once in Inventory → Units.
 */
class Uom extends Model
{
    use IsMaster;
    use SoftDeletes;

    protected $table = 'inv_units';

    protected $guarded = ['*'];

    protected $casts = ['is_active' => 'boolean'];

    /** Short form shown in BOM / cost sheet lines (PCS, YDS…). */
    public function getCodeAttribute(): string
    {
        return (string) ($this->short_name ?: $this->name);
    }
}
