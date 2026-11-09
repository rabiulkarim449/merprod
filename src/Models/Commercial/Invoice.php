<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Buyer;
use ME\MerchandisingSfl\Models\ShipMode;

/** Commercial Invoice (+ packing list figures) shipped against an Export LC. */
class Invoice extends Model
{
    use HasAudit;
    use SoftDeletes;

    protected $table = 'msfl_com_invoices';

    protected $fillable = [
        'invoice_no', 'invoice_date', 'export_lc_id', 'buyer_id', 'inv_shipment_id', 'exp_no', 'exp_date', 'ship_mode_id', 'bl_no', 'bl_date',
        'vessel', 'container_no', 'port_of_loading', 'port_of_discharge', 'final_destination', 'total_cartons', 'net_weight', 'gross_weight',
        'cbm', 'total_qty', 'total_value', 'remarks', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date', 'exp_date' => 'date', 'bl_date' => 'date',
        'net_weight' => 'decimal:2', 'gross_weight' => 'decimal:2', 'cbm' => 'decimal:3', 'total_value' => 'decimal:2',
    ];

    public function exportLc(): BelongsTo
    {
        return $this->belongsTo(ExportLc::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function shipMode(): BelongsTo
    {
        return $this->belongsTo(ShipMode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cartons(): HasMany
    {
        return $this->hasMany(InvoiceCarton::class)->orderBy('sort')->orderBy('carton_from');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** "US DOLLAR ONE THOUSAND FIVE HUNDRED FIVE AND CENTS 50 ONLY" for the printed invoice. */
    /** PL<YYMMDD>-<001> — the carton packing list number that goes with this invoice. */
    public function packingListNo(): string
    {
        return \ME\MerchandisingSfl\Services\DocumentNumberService::packingListNo((string) $this->invoice_no);
    }

    public function amountInWords(): string
    {
        $value = round((float) $this->total_value, 2);
        $whole = (int) floor($value);
        $cents = (int) round(($value - $whole) * 100);
        $words = class_exists(\NumberFormatter::class) ? (new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($whole) : (string) $whole;
        $currency = $this->exportLc?->currency?->name ?? '';

        return strtoupper(trim($currency . ' ' . str_replace('-', ' ', $words) . ($cents ? ' and cents ' . $cents : '') . ' only'));
    }

    /** Re-derive the totals from the lines. */
    public function refreshTotals(): void
    {
        $this->total_qty = (int) $this->lines()->sum('qty');
        $this->total_value = (float) $this->lines()->sum('amount');
        $this->total_cartons = (int) $this->lines()->sum('cartons');
        $this->save();
    }
}
