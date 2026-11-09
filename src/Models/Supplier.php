<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/**
 * Fabric / trims supplier.
 * Read-only here: entered once in Inventory → Suppliers.
 */
class Supplier extends Model
{
    use IsMaster;
    use SoftDeletes;

    protected $table = 'inv_suppliers';

    protected $guarded = ['*'];

    protected $casts = ['is_active' => 'boolean'];
}
