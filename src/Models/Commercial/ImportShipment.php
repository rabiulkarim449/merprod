<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\ShipMode;
use ME\MerchandisingSfl\Models\Supplier;

/** Raw-material import shipment under a BTB LC (B/L / AWB, ETD / ETA, arrival, document release) — record only. */
class ImportShipment extends Model
{
    use HasAudit;
    use SoftDeletes;

    protected $table = 'msfl_com_import_shipments';

    protected $fillable = [
        'btb_lc_id', 'supplier_id', 'invoice_no', 'invoice_value', 'ship_mode_id', 'bl_no', 'bl_date', 'etd', 'eta',
        'arrival_date', 'doc_release_date', 'goods_release_date', 'remarks', 'attachment', 'created_by',
    ];

    protected $casts = [
        'bl_date' => 'date', 'etd' => 'date', 'eta' => 'date', 'arrival_date' => 'date', 'doc_release_date' => 'date',
        'goods_release_date' => 'date', 'invoice_value' => 'decimal:2',
    ];

    public function btbLc(): BelongsTo
    {
        return $this->belongsTo(BtbLc::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shipMode(): BelongsTo
    {
        return $this->belongsTo(ShipMode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
