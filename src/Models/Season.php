<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class Season extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_seasons';

    protected $fillable = ['code', 'name', 'year', 'start_date', 'end_date', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];
}
