<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;
use ME\MerchandisingSfl\Models\Concerns\RequiresApproval;

/** Buyer — a new one is approved on the central Approvals page before it can be used. */
class Buyer extends Model
{
    use HasAudit;
    use IsMaster;
    use RequiresApproval;
    use SoftDeletes;

    public const APPROVAL_MODULE = 'msfl.buyer';

    public const APPROVAL_PERMISSION = 'msfl_buyer';

    protected $table = 'msfl_buyers';

    protected $fillable = ['code', 'name', 'short_name', 'merchandiser_id', 'country', 'agent_name', 'contact_person', 'phone', 'email', 'address', 'payment_term_id', 'delivery_term', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'approved_at' => 'datetime'];

    /** Pickable in forms (and by Inventory): active AND approved. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_active'), true)->approved();
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(Commercial\PaymentTerm::class);
    }

    public function merchandiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchandiser_id');
    }

    protected function approvalRouteName(): string
    {
        return 'msfl.masters.index';
    }

    protected function approvalRouteParams(): array
    {
        return ['master' => 'buyers', 'search' => $this->code ?: $this->name];
    }
}
