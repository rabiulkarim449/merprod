<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Currency;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Supplier;

/** A supplier's Proforma Invoice for fabric / accessories, kept on record with its PDF; a BTB LC is opened against it. */
class SupplierPi extends Model
{
    use HasAudit;
    use SoftDeletes;

    public const GOODS_TYPES = ['fabric' => 'Fabric', 'accessories' => 'Accessories / Trims', 'packing' => 'Packing', 'other' => 'Other'];

    protected $table = 'msfl_com_supplier_pis';

    protected $fillable = ['pi_no', 'pi_date', 'supplier_id', 'goods_type', 'order_id', 'export_lc_id', 'currency_id', 'pi_value', 'btb_lc_id', 'remarks', 'attachment', 'created_by'];

    protected $casts = ['pi_date' => 'date', 'pi_value' => 'decimal:2'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function exportLc(): BelongsTo
    {
        return $this->belongsTo(ExportLc::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function btbLc(): BelongsTo
    {
        return $this->belongsTo(BtbLc::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "PI-123 · Supplier (USD 1,200.00)" */
    public function label(): string
    {
        return $this->pi_no . ' · ' . ($this->supplier->name ?? '-') . ' (' . ($this->currency->code ?? '') . ' ' . number_format((float) $this->pi_value, 2) . ')';
    }
}
