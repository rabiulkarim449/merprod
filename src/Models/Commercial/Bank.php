<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** A bank used in commercial papers: ours (lien / advising / BTB), a buyer's (LC issuing) or a supplier's. */
class Bank extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    public const TYPES = ['our' => 'Our Bank', 'buyer' => "Buyer's Bank", 'supplier' => "Supplier's Bank", 'other' => 'Other'];

    protected $table = 'msfl_com_banks';

    protected $fillable = ['code', 'name', 'short_name', 'bank_type', 'branch', 'swift_code', 'ad_code', 'account_no', 'country', 'address', 'contact_person', 'phone', 'email', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    /** "Dutch-Bangla Bank — Gulshan (SWIFT DBBLBDDH)" */
    public function label(): string
    {
        return $this->name . ($this->branch ? ' — ' . $this->branch : '') . ($this->swift_code ? ' (SWIFT ' . $this->swift_code . ')' : '');
    }
}
