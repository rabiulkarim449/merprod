<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

/** T&A of one style of a confirmed order. Dates are worked out by TnaPlanner. */
class TnaPlan extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'active' => ['Active', 'primary'],
        'completed' => ['Completed', 'success'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    protected $table = 'msfl_tna_plans';

    protected $fillable = ['tna_no', 'order_id', 'style_id', 'tna_template_id', 'bulletin_id', 'order_qty', 'shipment_date', 'smv', 'remarks', 'created_by'];

    protected $casts = [
        'shipment_date' => 'date',
        'pcd_date' => 'date',
        'sewing_start_date' => 'date',
        'sewing_end_date' => 'date',
        'ex_factory_date' => 'date',
        'calculated_at' => 'datetime',
        'is_feasible' => 'boolean',
        'smv' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TnaTemplate::class, 'tna_template_id');
    }

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(Line::class, 'msfl_tna_plan_lines')->withPivot('daily_capacity')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TnaTask::class)->orderBy('sequence');
    }

    public function isEditable(): bool
    {
        return $this->status === 'active';
    }

    /** [done, total] of the steps that apply (N/A excluded). */
    public function progress(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        $applicable = $tasks->where('is_na', false);

        return [$applicable->whereNotNull('actual_date')->count(), $applicable->count()];
    }

    public function delayedCount(): int
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();

        return $tasks->filter(fn (TnaTask $task) => $task->state() === 'delayed')->count();
    }
}
