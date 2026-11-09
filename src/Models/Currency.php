<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class Currency extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_currencies';

    protected $fillable = ['code', 'name', 'symbol', 'exchange_rate', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'exchange_rate' => 'decimal:4'];
}
