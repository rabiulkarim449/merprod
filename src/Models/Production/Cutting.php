<?php

namespace ME\MerchandisingSfl\Models\Production;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\OrderPo;

/** One cutting (lay) for an order PO: pieces cut per size, parts cut, and its bundles. */
class Cutting extends Model
{
    use HasAudit;
    use SoftDeletes;

    protected $table = 'msfl_prod_cuttings';

    protected $fillable = ['cutting_no', 'order_po_id', 'cutting_date', 'table_no', 'lay_count', 'fabric_used', 'bundle_size', 'total_qty', 'remarks', 'created_by'];

    protected $casts = ['cutting_date' => 'date', 'fabric_used' => 'decimal:2'];

    public function orderPo(): BelongsTo
    {
        return $this->belongsTo(OrderPo::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(CuttingSize::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(CuttingPart::class);
    }

    public function bundles(): HasMany
    {
        return $this->hasMany(Bundle::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
