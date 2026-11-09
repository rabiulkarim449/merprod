<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** Garment part (Front, Back, Sleeve …) used by cutting, embroidery, QC / rework and defects. */
class GarmentPart extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_garment_parts';

    protected $fillable = ['code', 'name', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];
}
