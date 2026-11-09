<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/**
 * Garment / material colour.
 * Read-only here: entered once in Inventory → Colors.
 */
class Color extends Model
{
    use IsMaster;
    use SoftDeletes;

    protected $table = 'inv_colors';

    protected $guarded = ['*'];

    protected $casts = ['is_active' => 'boolean'];
}
