<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

class Bom extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'approved' => ['Approved', 'success'],
    ];

    public const TYPES = ['manual' => 'Create BOM', 'file' => 'Buyer File (PDF / Excel)'];

    protected $table = 'msfl_boms';

    protected $fillable = ['bom_no', 'style_id', 'order_id', 'version', 'bom_type', 'bom_file', 'remarks', 'created_by'];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function fileUrl(): ?string
    {
        return $this->bom_file ? \ME\MerchandisingSfl\Services\FileUploadService::link($this->bom_file) : null;
    }

    /** Order qty the line is booked against: the order's qty of this style, narrowed by the line's color / size. */
    public function orderQtyFor(BomItem $line): int
    {
        return $this->order ? $this->order->styleQty($this->style_id, $line->color_id, $line->size_id) : 0;
    }
}
