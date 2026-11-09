<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use ME\MerchandisingSfl\Models\Concerns\HasStatus;

/**
 * Operation bulletin of a style, in the factory's sheet format. Per operation:
 *
 *   Tar/Hr       = 60 ÷ SMV                      (one workplace, rounded)
 *   Req W-Place  = SMV × Target/Hr ÷ 60          (2 decimals)
 *   W-Place      = override, else ⌈Req W-Place⌉
 *   P.Target     = Tar/Hr × W-Place
 *   Bottleneck % = Req W-Place ÷ W-Place          (how loaded the workplaces are)
 *
 * Totals: Ttl MP = Σ W-Place (helpers = helper machine types, rest = operators),
 * R-SMV = Ttl MP × 60 ÷ Target/Hr, Utilization = SMV ÷ R-SMV, Max / Min = P.Target range,
 * Bottleneck % (sheet) = the highest operation Bottleneck %.
 * Operations switched off ("not done for this style") count nowhere.
 */
class Bulletin extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'approved' => ['Approved', 'success'],
    ];

    protected $table = 'msfl_bulletins';

    protected $fillable = ['bulletin_no', 'style_id', 'version', 'bulletin_date', 'description', 'line_id', 'target_per_hour', 'working_hours', 'remarks', 'created_by'];

    protected $casts = [
        'bulletin_date' => 'date',
        'working_hours' => 'decimal:1',
        'total_smv' => 'decimal:3',
        'r_smv' => 'decimal:2',
        'utilization_percent' => 'decimal:2',
        'bottleneck_percent' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(BulletinOperation::class)->orderBy('sequence');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    // ---- per operation -------------------------------------------------

    public function opTargetPerHour(BulletinOperation $op): int
    {
        return (float) $op->smv > 0 ? (int) round(60 / (float) $op->smv) : 0;
    }

    public function requiredWorkplaces(BulletinOperation $op): float
    {
        return round((float) $op->smv * $this->target_per_hour / 60, 2);
    }

    public function workplaces(BulletinOperation $op): int
    {
        if (! $op->is_active) {
            return 0;
        }

        return $op->workplaces ?? max(1, (int) ceil($this->requiredWorkplaces($op)));
    }

    public function pTarget(BulletinOperation $op): int
    {
        return $this->opTargetPerHour($op) * $this->workplaces($op);
    }

    public function bottleneckPercent(BulletinOperation $op): float
    {
        $workplaces = $this->workplaces($op);

        return $workplaces > 0 ? round($this->requiredWorkplaces($op) / $workplaces * 100) : 0;
    }

    // ---- totals --------------------------------------------------------

    public function recalculate(): void
    {
        $this->load('operations.machineType');
        $active = $this->operations->where('is_active', true);

        $helpers = $active->filter(fn ($op) => (bool) $op->machineType?->is_helper)->sum(fn ($op) => $this->workplaces($op));
        $manpower = $active->sum(fn ($op) => $this->workplaces($op));
        $pTargets = $active->map(fn ($op) => $this->pTarget($op))->filter();

        $this->total_smv = round($active->sum('smv'), 3);
        $this->target_per_day = (int) round($this->target_per_hour * (float) $this->working_hours);
        $this->helpers = $helpers;
        $this->operators = $manpower - $helpers;
        $this->total_manpower = $manpower;
        $this->r_smv = $this->target_per_hour > 0 ? round($manpower * 60 / $this->target_per_hour, 2) : 0;
        $this->utilization_percent = (float) $this->r_smv > 0 ? round((float) $this->total_smv / (float) $this->r_smv * 100, 2) : 0;
        $this->max_p_target = (int) ($pTargets->max() ?? 0);
        $this->min_p_target = (int) ($pTargets->min() ?? 0);
        $this->bottleneck_percent = (float) ($active->map(fn ($op) => $this->bottleneckPercent($op))->max() ?? 0);

        $this->save();
    }

    /** Active operations grouped by section, in order (a blank section continues the previous one). */
    public function sections(): Collection
    {
        $current = null;

        return $this->operations->groupBy(function ($op) use (&$current) {
            return $current = filled($op->section) ? $op->section : ($current ?? '');
        });
    }

    /**
     * Machine requirement: Σ W-Place per machine type, against the reference line.
     *
     * @return Collection<int, array{code: string, name: string, is_helper: bool, required: int, available: ?int, shortage: int}>
     */
    public function machineSummary(): Collection
    {
        \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes();
        $this->loadMissing(['operations.machineType', 'line.machines']);
        $available = $this->line ? $this->line->machineCounts() : collect();
        $required = $this->operations->where('is_active', true)
            ->groupBy(fn ($op) => $op->machine_type_id ?? 0)
            ->map(fn ($ops) => $ops->sum(fn ($op) => $this->workplaces($op)));

        return MachineType::query()->active()->orderBy('is_helper')->orderBy('code')->get()
            ->map(function (MachineType $type) use ($required, $available) {
                $need = (int) ($required[$type->id] ?? 0);
                $have = $this->line && ! $type->is_helper ? (int) ($available[$type->id] ?? 0) : null;

                return [
                    'code' => $type->code, 'name' => $type->name, 'is_helper' => $type->is_helper,
                    'required' => $need, 'available' => $have, 'shortage' => $have === null ? 0 : max(0, $need - $have),
                ];
            })
            ->when($required->has(0), fn ($rows) => $rows->push([
                'code' => '—', 'name' => 'No machine set', 'is_helper' => false, 'required' => (int) $required[0], 'available' => null, 'shortage' => 0,
            ]));
    }

    public function machineShortage(): int
    {
        return (int) $this->machineSummary()->sum('shortage');
    }
}
