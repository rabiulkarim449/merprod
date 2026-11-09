<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Buyer payment realized against a commercial invoice (PRC / ERC, bank charge) — record only. */
class Realization extends Model
{
    use HasAudit;
    use SoftDeletes;

    protected $table = 'msfl_com_realizations';

    protected $fillable = ['invoice_id', 'realization_date', 'bank_id', 'realized_amount', 'bank_charge', 'prc_no', 'prc_date', 'remarks', 'attachment', 'created_by'];

    protected $casts = ['realization_date' => 'date', 'prc_date' => 'date', 'realized_amount' => 'decimal:2', 'bank_charge' => 'decimal:2'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
