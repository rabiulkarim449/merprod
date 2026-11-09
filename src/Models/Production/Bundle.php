<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ME\MerchandisingSfl\Models\Size;

class Bundle extends Model
{
    public $timestamps = false;

    protected $table = 'msfl_prod_bundles';

    protected $fillable = ['cutting_id', 'bundle_no', 'size_id', 'qty'];

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class)->withTrashed();
    }
}
