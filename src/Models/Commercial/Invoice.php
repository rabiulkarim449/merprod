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
        'consignee_name', 'consignee_address', 'country_of_origin', 'shipment_date', 'exchange_rate',
        'discount', 'freight_other', 'advance_received', 'special_declaration',
    ];

    protected $casts = [
        'invoice_date' => 'date', 'exp_date' => 'date', 'bl_date' => 'date', 'shipment_date' => 'date',
        'net_weight' => 'decimal:2', 'gross_weight' => 'decimal:2', 'cbm' => 'decimal:3', 'total_value' => 'decimal:2',
        'exchange_rate' => 'decimal:4', 'discount' => 'decimal:2', 'freight_other' => 'decimal:2', 'advance_received' => 'decimal:2',
    ];

    /** Subtotal (Σ lines) − discount + freight / other. total_value stays the goods value (LC shipped value uses it). */
    public function invoiceTotal(): float
    {
        return round((float) $this->total_value - (float) $this->discount + (float) $this->freight_other, 2);
    }

    public function balanceDue(): float
    {
        return round($this->invoiceTotal() - (float) $this->advance_received, 2);
    }

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
        $value = $this->invoiceTotal();
        $whole = (int) floor($value);
        $cents = (int) round(($value - $whole) * 100);
        $words = class_exists(\NumberFormatter::class) ? (new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($whole) : self::spell($whole);
        $currency = $this->exportLc?->currency?->name ?? '';

        return strtoupper(trim($currency . ' ' . str_replace('-', ' ', $words) . ($cents ? ' and cents ' . $cents : '') . ' only'));
    }

    /** English words for a whole number — used when PHP's intl extension is not installed. */
    private static function spell(int $n): string
    {
        $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
            'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return $ones[intdiv($n, 100)] . ' hundred' . ($n % 100 ? ' ' . self::spell($n % 100) : '');
        }
        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1000 => 'thousand'] as $size => $name) {
            if ($n >= $size) {
                return self::spell(intdiv($n, $size)) . ' ' . $name . ($n % $size ? ' ' . self::spell($n % $size) : '');
            }
        }

        return (string) $n;
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
