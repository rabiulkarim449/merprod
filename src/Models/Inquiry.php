<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

class Inquiry extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'open' => ['Open', 'info'],
        'quoted' => ['Quoted', 'primary'],
        'confirmed' => ['Confirmed', 'success'],
        'lost' => ['Lost', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    protected $table = 'msfl_inquiries';

    protected $fillable = [
        'inquiry_no', 'inquiry_date', 'buyer_id', 'season_id', 'merchandiser_id', 'factory_id', 'product_type_id',
        'style_ref', 'color_ref', 'description', 'order_qty', 'unit_price', 'total_value',
        'confirmation_due_date', 'target_ship_date', 'extended_ship_date', 'status', 'lost_reason', 'remarks', 'created_by',
    ];

    protected $casts = [
        'inquiry_date' => 'date',
        'confirmation_due_date' => 'date',
        'target_ship_date' => 'date',
        'extended_ship_date' => 'date',
        'unit_price' => 'decimal:4',
        'total_value' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (Inquiry $inquiry) {
            $inquiry->total_value = $inquiry->order_qty !== null && $inquiry->unit_price !== null
                ? $inquiry->order_qty * $inquiry->unit_price
                : null;
        });
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

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    public function styles(): HasMany
    {
        return $this->hasMany(Style::class);
    }

    public function costSheets(): HasMany
    {
        return $this->hasMany(CostSheet::class);
    }
}
