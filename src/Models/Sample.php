<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

/**
 * One sample stage (Fit, PP, Size Set, ...) of a style. A rejected sample is
 * resubmitted as a new row (revision_no + 1, parent_sample_id = rejected one).
 */
class Sample extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'requested' => ['Requested', 'info'],
        'in_progress' => ['In Progress', 'primary'],
        'submitted' => ['Submitted', 'warning'],
        'approved' => ['Approved', 'success'],
        'rejected' => ['Rejected', 'danger'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    protected $table = 'msfl_samples';

    protected $fillable = [
        'sample_no', 'style_id', 'buyer_id', 'sample_type_id', 'order_id', 'merchandiser_id', 'revision_no', 'parent_sample_id',
        'request_date', 'required_date', 'qty', 'size_ref', 'color_ref', 'attachment', 'remarks', 'created_by',
    ];

    protected $casts = [
        'request_date' => 'date',
        'required_date' => 'date',
        'submit_date' => 'date',
        'decision_date' => 'date',
    ];

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function sampleType(): BelongsTo
    {
        return $this->belongsTo(SampleType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function merchandiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merchandiser_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_sample_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_sample_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SampleComment::class)->latest('comment_date')->latest('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['requested', 'in_progress'], true);
    }

    public function isOverdue(): bool
    {
        return $this->required_date
            && in_array($this->status, ['requested', 'in_progress'], true)
            && $this->required_date->isPast();
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment ? \ME\MerchandisingSfl\Services\FileUploadService::link($this->attachment) : null;
    }
}
