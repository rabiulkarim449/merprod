<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ME\MerchandisingSfl\Models\Line;
use ME\MerchandisingSfl\Models\MachineType;

/**
 * Machines are entered once — in Inventory → Machines, each with its type and
 * line. Planning reads them from there instead of asking again:
 *   - every Inventory machine type becomes a Machine Type here (linked by
 *     inv_type); planning only adds its short code and the helper flag;
 *   - a line's machines = active Inventory machines whose `line` matches the
 *     HR line name ("Line 2") or "Floor 1 · Line 2", counted per type.
 * Without the Inventory package nothing changes: types and line machine
 * quantities are entered by hand as before.
 */
class InventoryMachines
{
    private static bool $synced = false;

    /** @var array<string, array<string, int>>|null normalized line => [normalized type => qty] */
    private static ?array $counts = null;

    public static function available(): bool
    {
        return class_exists(\ME\SflInventory\Models\InvMachine::class)
            && \Illuminate\Support\Facades\Schema::hasColumn('msfl_machine_types', 'inv_type');
    }

    /**
     * Creates a Machine Type for each Inventory type not linked yet — adopting
     * an existing type with the same code or name instead of duplicating it.
     * Runs once per request; returns how many types were created or linked.
     */
    public static function syncTypes(): int
    {
        if (self::$synced || ! static::available()) {
            return 0;
        }
        self::$synced = true;

        $invTypes = DB::table('inv_machines')->whereNull('deleted_at')->whereNotNull('type')->where('type', '<>', '')
            ->distinct()->pluck('type')->map(fn ($t) => trim($t))->filter()->unique(fn ($t) => self::norm($t));

        $linked = MachineType::withTrashed()->whereNotNull('inv_type')->pluck('inv_type')->map(fn ($t) => self::norm($t))->all();
        $changed = 0;

        foreach ($invTypes as $invType) {
            if (in_array(self::norm($invType), $linked, true)) {
                continue;
            }

            $existing = MachineType::query()->whereNull('inv_type')
                ->where(fn ($q) => $q->whereRaw('LOWER(TRIM(code)) = ?', [self::norm($invType)])->orWhereRaw('LOWER(TRIM(name)) = ?', [self::norm($invType)]))
                ->first();

            if ($existing) {
                $existing->update(['inv_type' => $invType]);
            } else {
                MachineType::create([
                    'code' => self::uniqueCode($invType),
                    'name' => $invType,
                    'inv_type' => $invType,
                    'is_helper' => false,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
            }
            $changed++;
        }

        return $changed;
    }

    /**
     * Machines on a line from Inventory: machine_type_id => qty.
     *
     * @return Collection<int, int>
     */
    public static function forLine(Line $line): Collection
    {
        $counts = self::counts();
        $byType = [];
        foreach (array_unique([self::norm($line->code), self::norm($line->name)]) as $key) {
            foreach ($counts[$key] ?? [] as $type => $qty) {
                $byType[$type] = ($byType[$type] ?? 0) + $qty;
            }
        }

        $typeIds = self::typeIds();
        $result = [];
        foreach ($byType as $type => $qty) {
            if ($id = $typeIds[$type] ?? null) {
                $result[$id] = ($result[$id] ?? 0) + $qty;
            }
        }

        return collect($result);
    }

    /**
     * Inventory machines whose line matches no line set up here — shown as a
     * warning so the line names can be aligned. line text => qty.
     *
     * @return Collection<string, int>
     */
    public static function unmatchedLines(): Collection
    {
        if (! static::available()) {
            return collect();
        }

        $known = Line::query()->with('floorLine')->get()
            ->flatMap(fn ($l) => [self::norm($l->code), self::norm($l->name)])->filter()->unique()->all();

        return DB::table('inv_machines')->whereNull('deleted_at')->where('is_active', true)
            ->whereNotNull('line')->where('line', '<>', '')
            ->selectRaw('TRIM(line) AS line, COUNT(*) AS qty')->groupByRaw('TRIM(line)')->get()
            ->reject(fn ($r) => in_array(self::norm($r->line), $known, true))
            ->mapWithKeys(fn ($r) => [$r->line => (int) $r->qty]);
    }

    /** @return array<string, array<string, int>> */
    private static function counts(): array
    {
        if (self::$counts !== null) {
            return self::$counts;
        }

        self::$counts = [];
        if (! static::available()) {
            return self::$counts;
        }

        $rows = DB::table('inv_machines')->whereNull('deleted_at')->where('is_active', true)
            ->whereNotNull('line')->whereNotNull('type')
            ->selectRaw('TRIM(line) AS line, TRIM(type) AS type, COUNT(*) AS qty')
            ->groupByRaw('TRIM(line), TRIM(type)')->get();

        foreach ($rows as $row) {
            $line = self::norm($row->line);
            $type = self::norm($row->type);
            self::$counts[$line][$type] = (self::$counts[$line][$type] ?? 0) + (int) $row->qty;
        }

        return self::$counts;
    }

    /** @return array<string, int> normalized inv_type => machine_type_id */
    private static function typeIds(): array
    {
        return MachineType::query()->active()->whereNotNull('inv_type')->get(['id', 'inv_type'])
            ->mapWithKeys(fn ($t) => [self::norm($t->inv_type) => $t->id])->all();
    }

    private static function uniqueCode(string $type): string
    {
        $base = Str::limit(Str::upper(preg_replace('/\s+/', ' ', $type)), 45, '');
        $code = $base;
        for ($n = 2; MachineType::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
    }

    private static function norm(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /** Test hook: forget the per-request caches. */
    public static function flush(): void
    {
        self::$synced = false;
        self::$counts = null;
    }
}
