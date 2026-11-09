<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** Payment term of an LC / contract: at sight, usance n days, TT, DP / DA — days count from the B/L (document) date. */
class PaymentTerm extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    public const TYPES = [
        'sight' => 'LC at Sight', 'usance' => 'LC Usance (deferred)', 'tt_advance' => 'TT in Advance', 'tt' => 'TT after Shipment',
        'dp' => 'DP (Documents against Payment)', 'da' => 'DA (Documents against Acceptance)',
    ];

    protected $table = 'msfl_com_payment_terms';

    protected $fillable = ['code', 'name', 'term_type', 'days', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'days' => 'integer'];
}
