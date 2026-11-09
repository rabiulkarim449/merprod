<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ME\MerchandisingSfl\Models\OrderPo;

/** One PO shipped on a commercial invoice: qty × the PO's FOB. */
class InvoiceLine extends Model
{
    protected $table = 'msfl_com_invoice_lines';

    protected $fillable = ['invoice_id', 'order_po_id', 'qty', 'unit_price', 'amount', 'cartons'];

    protected $casts = ['unit_price' => 'decimal:4', 'amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(fn (InvoiceLine $line) => $line->amount = round($line->qty * (float) $line->unit_price, 2));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }
}
