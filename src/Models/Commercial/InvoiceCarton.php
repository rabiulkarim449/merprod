<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * One carton range of a commercial invoice (cartons carton_from … carton_to, all packed
 * alike): pcs per size per carton, weight per carton (kg) and carton size (cm).
 */
class InvoiceCarton extends Model
{
    protected $table = 'msfl_com_invoice_cartons';

    protected $fillable = ['invoice_id', 'order_po_id', 'carton_from', 'carton_to', 'net_weight', 'gross_weight', 'length', 'width', 'height', 'sort'];

    protected $casts = ['net_weight' => 'decimal:2', 'gross_weight' => 'decimal:2', 'length' => 'decimal:1', 'width' => 'decimal:1', 'height' => 'decimal:1'];

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(InvoiceCartonSize::class);
    }

    public function cartonCount(): int
    {
        return max(0, (int) $this->carton_to - (int) $this->carton_from + 1);
    }

    public function pcsPerCarton(): int
    {
        return (int) $this->sizes->sum('qty');
    }

    public function totalPcs(): int
    {
        return $this->pcsPerCarton() * $this->cartonCount();
    }

    /** m³ of the whole range (L × W × H cm per carton). */
    public function cbm(): float
    {
        return $this->length && $this->width && $this->height
            ? round((float) $this->length * (float) $this->width * (float) $this->height / 1000000 * $this->cartonCount(), 3)
            : 0.0;
    }
}
