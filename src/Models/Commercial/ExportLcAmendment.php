<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer amendment of an active Export LC (value / last shipment / expiry). */
class ExportLcAmendment extends Model
{
    protected $table = 'msfl_com_export_lc_amendments';

    protected $fillable = ['export_lc_id', 'amendment_no', 'amendment_date', 'field', 'old_value', 'new_value', 'remarks', 'changed_by'];

    protected $casts = ['amendment_date' => 'date'];

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
