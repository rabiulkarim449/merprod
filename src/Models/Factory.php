<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class Factory extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_factories';

    protected $fillable = ['code', 'name', 'address', 'capacity_per_month', 'is_own', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'is_own' => 'boolean'];
}
