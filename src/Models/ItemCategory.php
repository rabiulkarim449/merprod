<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class ItemCategory extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_item_categories';

    protected $fillable = ['code', 'name', 'type', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];
}
