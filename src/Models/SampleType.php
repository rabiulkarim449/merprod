<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class SampleType extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_sample_types';

    protected $fillable = ['code', 'name', 'sequence', 'requires_buyer_approval', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'requires_buyer_approval' => 'boolean'];
}
