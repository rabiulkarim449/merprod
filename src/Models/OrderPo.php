<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One row = one PO + one style + one color, with its size breakdown. */
class OrderPo extends Model
{
    use HasAudit;

    protected $table = 'msfl_order_pos';

    protected $fillable = [
        'order_id', 'style_id', 'color_id', 'po_no', 'unit_price', 'pcd_date', 'shipment_date', 'ship_mode_id', 'remarks',
        'needs_embroidery', 'needs_washing', 'applique_ih', 'studs_stones_ih', 'heat_seal_ih',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'total_value' => 'decimal:4',
        'pcd_date' => 'date',
        'shipment_date' => 'date',
        'needs_embroidery' => 'boolean',
        'needs_washing' => 'boolean',
        'applique_ih' => 'boolean',
        'studs_stones_ih' => 'boolean',
        'heat_seal_ih' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function shipMode(): BelongsTo
    {
        return $this->belongsTo(ShipMode::class);
    }

    /** Buyer revisions after confirmation, oldest first. */
    public function revisions(): HasMany
    {
        return $this->hasMany(OrderPoRevision::class)->orderBy('changed_at')->orderBy('id');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(OrderPoSize::class);
    }

    /** Replace the size breakdown ([size_id => qty]) and re-derive qty / value. */
    public function syncSizes(array $qtyBySize): void
    {
        $this->sizes()->delete();

        foreach ($qtyBySize as $sizeId => $qty) {
            if ((int) $qty > 0) {
                $this->sizes()->create(['size_id' => $sizeId, 'qty' => (int) $qty]);
            }
        }

        $this->po_qty = (int) $this->sizes()->sum('qty');
        $this->total_value = round($this->po_qty * (float) $this->unit_price, 4);
        $this->save();
    }

    /** "ORD-2026-0001 · PO 4500 · ST-01 · Black" — how a PO is picked in Production. */
    public function label(): string
    {
        return implode(' · ', array_filter([$this->order->order_no ?? null, 'PO ' . $this->po_no, $this->style->style_no ?? null, $this->color->name ?? null]));
    }
}
