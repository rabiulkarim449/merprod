<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One buyer revision of a confirmed PO line: its qty, PCD or shipment date changed. */
class OrderPoRevision extends Model
{
    public const FIELDS = ['po_qty' => 'PO Qty', 'pcd_date' => 'PCD', 'shipment_date' => 'Shipment Date'];

    public $timestamps = false;

    protected $table = 'msfl_order_po_revisions';

    protected $fillable = ['order_po_id', 'field', 'old_value', 'new_value', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
