<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

class Order extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'confirmed' => ['Confirmed', 'success'],
        'closed' => ['Closed', 'dark'],
        'cancelled' => ['Cancelled', 'danger'],
    ];

    /** Allowed status changes: current => [target, ...]. */
    public const TRANSITIONS = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['closed', 'cancelled'],
    ];

    public const DELIVERY_TERMS = ['FOB' => 'FOB', 'CIF' => 'CIF', 'CMT' => 'CMT', 'DDP' => 'DDP', 'EXW' => 'EXW'];

    protected $table = 'msfl_orders';

    protected $fillable = [
        'order_no', 'buyer_id', 'season_id', 'merchandiser_id', 'factory_id', 'inquiry_id', 'buyer_order_ref',
        'order_date', 'currency_id', 'delivery_term', 'payment_term_id', 'attachment', 'remarks', 'is_quick_entry', 'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'confirmed_at' => 'datetime',
        'total_value' => 'decimal:4',
        'is_quick_entry' => 'boolean',
    ];

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(Commercial\PaymentTerm::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function merchandiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchandiser_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function pos(): HasMany
    {
        return $this->hasMany(OrderPo::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    /** Header and PO lines stay editable until the order is closed or cancelled (PO revisions after confirmation). */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'confirmed'], true);
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Re-derive the cached totals from the PO lines. */
    public function refreshTotals(): void
    {
        $this->total_qty = (int) $this->pos()->sum('po_qty');
        $this->total_value = (float) $this->pos()->sum('total_value');
        $this->save();
    }

    /** Order qty of one style, optionally narrowed to a color and/or size (used by the Order BOM). */
    public function styleQty(int $styleId, ?int $colorId = null, ?int $sizeId = null): int
    {
        $pos = $this->pos()->where('style_id', $styleId)->when($colorId, fn ($q) => $q->where('color_id', $colorId));

        if (! $sizeId) {
            return (int) $pos->sum('po_qty');
        }

        return (int) OrderPoSize::query()->whereIn('order_po_id', $pos->select('id'))->where('size_id', $sizeId)->sum('qty');
    }
}
