<?php

namespace ME\MerchandisingSfl\Models\Production;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ME\MerchandisingSfl\Models\OrderPo;

/**
 * Link from an order PO to the Inventory requisition raised for it. The
 * requisition itself (approval, issue, stock) lives in Inventory.
 */
class FabricRequisition extends Model
{
    protected $table = 'msfl_prod_fabric_requisitions';

    protected $fillable = ['order_po_id', 'inv_requisition_id', 'created_by'];

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(\ME\SflInventory\Models\InvRequisition::class, 'inv_requisition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
