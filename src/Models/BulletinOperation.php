<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinOperation extends Model
{
    protected $table = 'msfl_bulletin_operations';

    protected $fillable = ['bulletin_id', 'sequence', 'section', 'operation_id', 'name', 'machine_type_id', 'attachment', 'smv', 'workplaces', 'is_active', 'remarks'];

    protected $casts = ['smv' => 'decimal:3', 'is_active' => 'boolean'];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }
}
