<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Buyer;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;
use ME\MerchandisingSfl\Models\Currency;
use ME\MerchandisingSfl\Models\OrderPo;

/** Export LC / Sales Contract from a buyer, with the PO lines it covers. */
class ExportLc extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'active' => ['Active', 'success'],
        'closed' => ['Closed', 'dark'],
    ];

    public const TYPES = ['lc' => 'Export LC', 'sc' => 'Sales Contract'];

    /** Fields whose change after activation is a buyer amendment. */
    public const AMENDED = ['lc_value' => 'LC Value', 'last_shipment_date' => 'Last Shipment Date', 'expiry_date' => 'Expiry Date'];

    protected $table = 'msfl_com_export_lcs';

    protected $fillable = [
        'lc_no', 'type', 'buyer_lc_no', 'buyer_id', 'currency_id', 'lc_date', 'last_shipment_date', 'expiry_date', 'lc_value',
        'tolerance_percent', 'payment_term_id', 'issuing_bank_id', 'lien_bank_id', 'remarks', 'attachment', 'created_by',
    ];

    protected $casts = [
        'lc_date' => 'date', 'last_shipment_date' => 'date', 'expiry_date' => 'date',
        'lc_value' => 'decimal:2', 'tolerance_percent' => 'decimal:2',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    /** The buyer's bank that issued the LC. */
    public function issuingBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'issuing_bank_id');
    }

    /** Our bank where the LC is lien / advised. */
    public function lienBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'lien_bank_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pos(): BelongsToMany
    {
        return $this->belongsToMany(OrderPo::class, 'msfl_com_export_lc_pos', 'export_lc_id', 'order_po_id')->withTimestamps();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ExportLcAmendment::class)->orderBy('amendment_no')->orderBy('id');
    }

    public function isEditable(): bool
    {
        return $this->status !== 'closed';
    }

    /** "ELC-2026-0001 · LC 1234 (H&M)" for selects. */
    public function label(): string
    {
        return $this->lc_no . ' · ' . strtoupper($this->type) . ' ' . $this->buyer_lc_no . ($this->buyer ? ' (' . $this->buyer->name . ')' : '');
    }

    public function paymentTermLabel(): string
    {
        return $this->paymentTerm->name ?? '-';
    }

    /** Most that may be shipped: LC value + tolerance. */
    public function maxValue(): float
    {
        return round((float) $this->lc_value * (1 + (float) $this->tolerance_percent / 100), 2);
    }
}
