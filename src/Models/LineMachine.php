<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineMachine extends Model
{
    protected $table = 'msfl_line_machines';

    protected $fillable = ['line_id', 'machine_type_id', 'qty'];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function machineType(): BelongsTo
    {
        return $this->belongsTo(MachineType::class);
    }
}
