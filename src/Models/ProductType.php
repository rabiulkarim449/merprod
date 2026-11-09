<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class ProductType extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_product_types';

    protected $fillable = ['code', 'name', 'category', 'default_smv', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'default_smv' => 'decimal:2'];
}
