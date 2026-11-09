<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class MachineType extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_machine_types';

    protected $fillable = ['code', 'name', 'inv_type', 'is_helper', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'is_helper' => 'boolean'];
}
