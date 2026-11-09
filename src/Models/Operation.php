<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** Operations library — reusable sewing operations for bulletins. */
class Operation extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_operations';

    protected $fillable = ['code', 'name', 'machine_type_id', 'garment_part_id', 'attachment', 'default_smv', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'default_smv' => 'decimal:3'];

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }

    public function garmentPart(): BelongsTo
    {
        return $this->belongsTo(GarmentPart::class);
    }
}
