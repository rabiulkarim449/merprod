<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TnaTask extends Model
{
    /** Display state => [label, badge color]. */
    public const STATES = [
        'done' => ['Done', 'success'],
        'done_late' => ['Done (Late)', 'warning'],
        'delayed' => ['Delayed', 'danger'],
        'due_soon' => ['Due Soon', 'warning'],
        'pending' => ['Pending', 'secondary'],
        'na' => ['N/A', 'light'],
    ];

    public const DUE_SOON_DAYS = 3;

    protected $table = 'msfl_tna_tasks';

    protected $fillable = [
        'tna_plan_id', 'tna_template_task_id', 'sequence', 'group_name', 'task_code', 'task_name', 'anchor', 'offset_days',
        'auto_source', 'is_mandatory', 'plan_date', 'revised_date', 'actual_date', 'is_na', 'responsible_id', 'remarks',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'revised_date' => 'date',
        'actual_date' => 'date',
        'is_na' => 'boolean',
        'is_mandatory' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TnaPlan::class, 'tna_plan_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** The date the step is due: the revised date if one was agreed, otherwise the planned date. */
    public function dueDate(): ?Carbon
    {
        return $this->revised_date ?? $this->plan_date;
    }

    public function isAuto(): bool
    {
        return $this->auto_source !== 'none';
    }

    public function state(): string
    {
        $due = $this->dueDate();

        return match (true) {
            $this->is_na => 'na',
            $this->actual_date !== null => $due && $this->actual_date->gt($due) ? 'done_late' : 'done',
            $due === null => 'pending',
            $due->lt(today()) => 'delayed',
            $due->lte(today()->addDays(self::DUE_SOON_DAYS)) => 'due_soon',
            default => 'pending',
        };
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state()][0];
    }

    public function stateBadge(): string
    {
        return self::STATES[$this->state()][1];
    }

    public function ruleText(): string
    {
        return TnaTemplateTask::ruleText($this->anchor, $this->offset_days);
    }
}
