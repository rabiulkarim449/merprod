<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;
use ME\MerchandisingSfl\Services\InventoryMachines;

/**
 * Planning figures (headcount, working day, efficiency) for an HR floor-line.
 * The floor / line itself is entered once in HR; machines come from Inventory
 * when it is installed.
 */
class Line extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_lines';

    protected $fillable = ['hr_floor_line_id', 'operators', 'helpers', 'working_minutes', 'efficiency_percent', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'efficiency_percent' => 'decimal:2'];

    public function floorLine(): BelongsTo
    {
        return $this->belongsTo(FloorLine::class, 'hr_floor_line_id');
    }

    /** HR line name, e.g. "Line 2" — also what Inventory machines use as their Line. */
    public function getCodeAttribute(): string
    {
        return (string) ($this->floorLine->line_name ?? '');
    }

    /** "Floor 1 · Line 2" */
    public function getNameAttribute(): string
    {
        return (string) ($this->floorLine->label ?? '');
    }

    public function getFloorAttribute(): ?string
    {
        return $this->floorLine->floor_name ?? null;
    }

    public function machines(): HasMany
    {
        return $this->hasMany(LineMachine::class);
    }

    public function tnaPlans(): BelongsToMany
    {
        return $this->belongsToMany(TnaPlan::class, 'msfl_tna_plan_lines')->withPivot('daily_capacity');
    }

    /** Pieces per day for a style of this SMV = operators × minutes × efficiency ÷ SMV. */
    public function dailyCapacity(float $smv): int
    {
        if ($smv <= 0) {
            return 0;
        }

        return (int) floor($this->operators * $this->working_minutes * ((float) $this->efficiency_percent / 100) / $smv);
    }

    /**
     * Machines on this line, machine_type_id => qty: counted from Inventory
     * when it is installed (machines are entered there once), otherwise the
     * quantities typed on the line form.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function machineCounts(): \Illuminate\Support\Collection
    {
        return InventoryMachines::available()
            ? InventoryMachines::forLine($this)
            : $this->machines->pluck('qty', 'machine_type_id')->map(fn ($q) => (int) $q);
    }

    public function totalMachines(): int
    {
        return (int) $this->machineCounts()->sum();
    }
}
