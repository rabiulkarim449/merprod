<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Currency;
use ME\MerchandisingSfl\Models\Supplier;

/** Back-to-Back LC opened with our bank against supplier PI(s), under a Master (Export) LC — record only. */
class BtbLc extends Model
{
    use HasAudit;
    use SoftDeletes;

    protected $table = 'msfl_com_btb_lcs';

    protected $fillable = [
        'btb_lc_no', 'lc_date', 'export_lc_id', 'supplier_id', 'bank_id', 'currency_id', 'lc_value', 'payment_term_id',
        'last_shipment_date', 'expiry_date', 'maturity_date', 'remarks', 'attachment', 'created_by',
    ];

    protected $casts = ['lc_date' => 'date', 'last_shipment_date' => 'date', 'expiry_date' => 'date', 'maturity_date' => 'date', 'lc_value' => 'decimal:2'];

    public function exportLc(): BelongsTo
    {
        return $this->belongsTo(ExportLc::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplierPis(): HasMany
    {
        return $this->hasMany(SupplierPi::class);
    }

    public function importShipments(): HasMany
    {
        return $this->hasMany(ImportShipment::class);
    }

    /** "BTB-123 · Supplier (USD 1,200.00)" */
    public function label(): string
    {
        return $this->btb_lc_no . ' · ' . ($this->supplier->name ?? '-') . ' (' . ($this->currency->code ?? '') . ' ' . number_format((float) $this->lc_value, 2) . ')';
    }
}
