<?php

namespace ME\MerchandisingSfl\Models\Production;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * Final Packing List of one buyer PO (every style line with that PO no on the order),
 * made after floor packing and approved by the buyer. Figures are live from production
 * until approval, then frozen in `snapshot`.
 */
class FinalPackingList extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'approved' => ['Buyer Approved', 'success'],
    ];

    protected $table = 'msfl_prod_final_packing_lists';

    protected $fillable = ['fpl_no', 'order_id', 'po_no', 'pack_date', 'invoice_no', 'buyer_approved_on', 'buyer_approved_by', 'approval_file', 'remarks', 'created_by'];

    protected $casts = ['pack_date' => 'date', 'buyer_approved_on' => 'date', 'snapshot' => 'array'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(FinalPackingListNote::class);
    }

    /** The style lines of this PO on the order. */
    public function poLines(): Builder
    {
        return OrderPo::query()->where('order_id', $this->order_id)->where('po_no', $this->po_no);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
