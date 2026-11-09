<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ME\MerchandisingSfl\Models\Size;

class CuttingSize extends Model
{
    public $timestamps = false;

    protected $table = 'msfl_prod_cutting_sizes';

    protected $fillable = ['cutting_id', 'size_id', 'qty'];

    public function cutting(): BelongsTo
    {
        return $this->belongsTo(Cutting::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class)->withTrashed();
    }
}
