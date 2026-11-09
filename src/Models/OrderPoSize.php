<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPoSize extends Model
{
    protected $table = 'msfl_order_po_sizes';

    protected $fillable = ['order_po_id', 'size_id', 'qty'];

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }
}
