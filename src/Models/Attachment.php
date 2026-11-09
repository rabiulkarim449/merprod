<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** Work-aid types (Guide, Pattern, Cutter, ...) pickable on an Operation Library entry / bulletin row. */
class Attachment extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_attachments';

    protected $fillable = ['code', 'name', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];
}
