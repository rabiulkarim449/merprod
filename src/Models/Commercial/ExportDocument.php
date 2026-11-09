<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An export paper of a commercial invoice (CO, GSP / Form-A, EXP, Shipping Advice …) — number, date and file, record only. */
class ExportDocument extends Model
{
    use HasAudit;
    use SoftDeletes;

    public const TYPES = [
        'co' => 'Certificate of Origin',
        'gsp' => 'GSP / Form-A',
        'exp' => 'EXP Form',
        'shipping_advice' => 'Shipping Advice',
        'booking' => 'Booking Confirmation',
        'bl' => 'B/L / AWB',
        'other' => 'Other',
    ];

    protected $table = 'msfl_com_export_documents';

    protected $fillable = ['invoice_id', 'doc_type', 'doc_no', 'doc_date', 'forwarder', 'booking_date', 'remarks', 'attachment', 'created_by'];

    protected $casts = ['doc_date' => 'date', 'booking_date' => 'date'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
