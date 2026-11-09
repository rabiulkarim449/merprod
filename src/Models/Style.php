<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

class Style extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    // Stored in development_status; HasStatus reads $this->status.
    public const STATUSES = [
        'new' => ['New', 'info'],
        'in_development' => ['In Development', 'primary'],
        'sample_stage' => ['Sample Stage', 'warning'],
        'approved' => ['Approved', 'success'],
        'dropped' => ['Dropped', 'secondary'],
    ];

    protected $table = 'msfl_styles';

    protected $fillable = [
        'style_no', 'name', 'buyer_id', 'inquiry_id', 'season_id', 'merchandiser_id', 'product_type_id', 'color_id', 'wash_type_id',
        'smv', 'target_cm', 'confirm_cm', 'fabric_sourced_by', 'fabric_description', 'description',
        'tech_pack_file', 'artwork_file', 'size_chart_file', 'development_status', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'smv' => 'decimal:2',
        'target_cm' => 'decimal:4',
        'confirm_cm' => 'decimal:4',
    ];

    public function getStatusAttribute(): ?string
    {
        return $this->development_status;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Tech pack details (Dev → Tech Pack). Style No / Name / Buyer / Season /
     * Product Type come from Master Data → Styles; these are added on top.
     */
    public const TECH_PACK_FIELDS = [
        'inquiry_id', 'merchandiser_id', 'wash_type_id', 'smv', 'target_cm', 'confirm_cm',
        'fabric_description', 'description', 'tech_pack_file', 'artwork_file', 'size_chart_file',
    ];

    /** Master styles nobody has written a tech pack for yet (offered on Tech Pack → New). */
    public function scopeWithoutTechPack(Builder $query): Builder
    {
        foreach (self::TECH_PACK_FIELDS as $field) {
            $query->whereNull($this->qualifyColumn($field));
        }

        return $query;
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function merchandiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchandiser_id');
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }

    /** The style's color (Inventory) — PO lines of the style take it. */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function washType(): BelongsTo
    {
        return $this->belongsTo(WashType::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(StyleImage::class);
    }

    public function costSheets(): HasMany
    {
        return $this->hasMany(CostSheet::class);
    }

    public function orderPos(): HasMany
    {
        return $this->hasMany(OrderPo::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class);
    }

    /** "S-001 — Basic Hoodie" for dropdowns. */
    public function label(): string
    {
        return $this->style_no . ' — ' . $this->name;
    }
}
