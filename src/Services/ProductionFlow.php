<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\OrderPo;
use ME\MerchandisingSfl\Models\Production\Cutting;
use ME\MerchandisingSfl\Models\Production\CuttingSize;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Models\Production\Entry;

/**
 * The production route of a PO and its piece balances.
 *
 *   cutting → [embroidery] → sewing → [washing] → finishing → final QC → packing
 *
 * Embroidery and washing apply only when the PO says so. Each stage takes in
 * what the previous stage passed (cutting "passes" every piece it cuts):
 *   can take in = previous stage's pass − this stage's input
 *   WIP         = input − pass − reject   (rework stays in WIP until it passes)
 *
 * QC / Rework screens (entry kind, any stage incl. cutting):
 *   qc     — pieces the stage already passed are checked: pass (stays passed, just
 *            counted as checked) / reject (leaves the flow). Older QC entries may
 *            also carry rework found.
 *   rework — rework found among passed pieces (rework_qty: leaves pass, back to
 *            WIP) and / or rework fixed (pass / reject out of WIP).
 *
 * Embroidery (part-wise) works out of cutting: cut parts are sent to embroidery
 * and come back to cutting. Cutting balance = cut − parts still out at
 * embroidery (sent − returned, rejects never come back) − taken by sewing;
 * sewing takes from that balance (styles without embroidery: cut − sewing input).
 *
 * Sewing records input by date and output / reject / rework by the hour
 * (hour_slot), per line (SewingController, msfl_prod_sewing_plans).
 */
class ProductionFlow
{
    /** Entry stages (cutting has its own screen). key => [label, icon, uses a sewing line] */
    public const STAGES = [
        'embroidery' => ['Embroidery', 'fa-solid fa-spa', false],
        'sewing' => ['Sewing', 'fa-solid fa-shirt', true],
        'washing' => ['Washing', 'fa-solid fa-soap', false],
        'finishing' => ['Finishing', 'fa-solid fa-wand-magic-sparkles', false],
        'final_qc' => ['Buyer QC', 'fa-solid fa-clipboard-check', false],
        'packing' => ['Packing', 'fa-solid fa-box', false],
    ];

    /** Entry kinds: production (stage screens), QC and Rework screens. */
    public const KINDS = [
        'production' => 'Production',
        'qc' => 'QC',
        'rework' => 'Rework',
    ];

    /**
     * Stages whose input and output are separate entries on their own dates: embroidery / washing
     * (sent out, received back) and finishing / packing (received in, output done).
     */
    public const SPLIT_STAGES = ['embroidery', 'washing', 'finishing', 'packing'];

    /** Labels of a split stage's two entries: [input, output]. */
    public static function splitLabels(string $stage): array
    {
        return match ($stage) {
            'embroidery' => ['Send to Embroidery (from Cutting)', 'Return to Cutting (from Embroidery)'],
            'finishing' => ['Receive in Finishing', 'Finishing Output'],
            'packing' => ['Receive in Packing', 'Packed (Output)'],
            default => ['Send to ' . self::label($stage), 'Receive from ' . self::label($stage)],
        };
    }

    /** Net pieces an entry adds to its stage's pass (QC / rework found take pieces back out). */
    public const NET_PASS_SQL = "CASE WHEN kind = 'qc' THEN -(reject_qty + rework_qty) WHEN kind = 'rework' THEN pass_qty - rework_qty ELSE pass_qty END";

    /** Sewing hour slots: start hour => label ("8-9 AM"); the break hour has no entry. */
    public static function hourSlots(): array
    {
        $out = [];
        foreach (config('merchandising-sfl.sewing_hours', range(8, 19)) as $h) {
            $out[$h] = date('g', mktime($h, 0)) . '-' . date('g A', mktime($h + 1, 0));
        }

        return $out;
    }

    public static function breakHour(): ?int
    {
        return config('merchandising-sfl.sewing_break_hour', 13);
    }

    public static function label(string $stage): string
    {
        return $stage === 'cutting' ? 'Cutting' : (self::STAGES[$stage][0] ?? ucfirst($stage));
    }

    /** Stages worked part by part (cut parts sent out and passed back to cutting). */
    public const PART_STAGES = ['embroidery'];

    /** Stages whose QC / rework names the part. */
    public static function partWise(string $stage): bool
    {
        return $stage === 'cutting' || in_array($stage, self::PART_STAGES, true);
    }

    public static function usesLine(string $stage): bool
    {
        return (bool) (self::STAGES[$stage][2] ?? false);
    }

    /** @return list<string> the PO's stages in order, cutting first */
    public function route(OrderPo $po): array
    {
        return array_values(array_filter([
            'cutting',
            $po->needs_embroidery ? 'embroidery' : null,
            'sewing',
            $po->needs_washing ? 'washing' : null,
            'finishing', 'final_qc', 'packing',
        ]));
    }

    public function previous(OrderPo $po, string $stage): ?string
    {
        $route = $this->route($po);
        $i = array_search($stage, $route, true);

        return $i ? $route[$i - 1] : null;
    }

    /**
     * Totals per stage on the PO's route:
     * stage => [input, pass, rework, reject, wip, available].
     * Embroidery is part-wise: its row also has 'parts' => [part => same keys]; the
     * stage pass is the garments whose every embroidery part came back (min over
     * parts), and sewing takes from min(cutting pass, that).
     * $ignoreEntryId leaves one entry out (used when re-checking an edit).
     * $sizeId: the same figures for one size only (cut of that size, entries of that size).
     *
     * @return array<string, array<string, mixed>>
     */
    public function summary(OrderPo $po, ?int $ignoreEntryId = null, ?int $sizeId = null): array
    {
        $cut = $sizeId
            ? (int) CuttingSize::query()->where('size_id', $sizeId)
                ->whereHas('cutting', fn ($q) => $q->where('order_po_id', $po->id))->sum('qty')
            : (int) Cutting::query()->where('order_po_id', $po->id)->sum('total_qty');

        // P = pass of production + rework-fixed entries; qo = pieces taken back out of pass
        // (QC reject / older QC rework, rework found); qrw = of those, back into WIP.
        $sums = Entry::query()->where('order_po_id', $po->id)
            ->when($ignoreEntryId, fn ($q) => $q->whereKeyNot($ignoreEntryId))
            ->when($sizeId, fn ($q) => $q->where('size_id', $sizeId))
            ->selectRaw("stage, CASE WHEN stage IN ('" . implode("','", self::PART_STAGES) . "') THEN COALESCE(part_name, '') ELSE '' END part, SUM(input_qty) i,
                SUM(CASE WHEN kind = 'qc' THEN 0 ELSE pass_qty END) p,
                SUM(CASE WHEN kind = 'qc' THEN reject_qty + rework_qty WHEN kind = 'rework' THEN rework_qty ELSE 0 END) qo,
                SUM(CASE WHEN kind IN ('qc', 'rework') THEN rework_qty ELSE 0 END) qrw,
                SUM(CASE WHEN kind = 'qc' THEN 0 ELSE reject_qty END) prj,
                SUM(rework_qty) rw, SUM(reject_qty) rj")
            ->groupBy('stage', 'part')->get()->groupBy('stage');
        $sewn = (int) ($sums->get('sewing')?->first()->i ?? 0);

        $row = fn ($s, int $input, int $passed, int $supplied) => [
            'input' => $input,
            'pass' => max(0, $passed - (int) ($s->qo ?? 0)),
            'rework' => (int) ($s->rw ?? 0),
            'reject' => (int) ($s->rj ?? 0),
            'wip' => max(0, $input - $passed - (int) ($s->prj ?? 0) + (int) ($s->qrw ?? 0)),
            'available' => max(0, $supplied - $input),
        ];

        $out = [];
        $supplied = 0;
        $cutPass = 0;
        foreach ($this->route($po) as $stage) {
            $rows = $sums->get($stage, collect());
            if ($stage === 'cutting') {
                // Cutting has no production entries: everything cut counts as passed.
                $s = $rows->first();
                $out[$stage] = ['available' => 0] + $row($s, $cut, (int) ($s->p ?? 0) + $cut, 0);
                $supplied = $cutPass = $out[$stage]['pass'];
                continue;
            }
            if (in_array($stage, self::PART_STAGES, true)) {
                // Parts go out from cutting and come back to it: 'out' = still away (or rejected there).
                $parts = $rows->mapWithKeys(function ($s) use ($row, $cutPass, $sewn) {
                    $r = $row($s, (int) $s->i, (int) $s->p, $cutPass);
                    $r['out'] = max(0, $r['input'] - $r['pass']);
                    $r['available'] = $this->partAvailable($cutPass, $sewn, $r['input'], $r['out']);

                    return [($s->part ?: '(no part)') => $r];
                })->all();
                $p = collect($parts);
                $maxOut = (int) ($p->max('out') ?? 0);
                $out[$stage] = [
                    'input' => (int) $p->max('input'),
                    'pass' => (int) $p->max('pass'),
                    'rework' => (int) $p->sum('rework'),
                    'reject' => (int) $p->sum('reject'),
                    'wip' => (int) $p->sum('wip'),
                    'out' => $maxOut,
                    'available' => max(0, $cutPass - $sewn - $maxOut),
                    'parts' => $parts,
                ];
                // Sewing takes from the cutting balance: what was cut minus what is still at embroidery.
                $supplied = max(0, $cutPass - $maxOut);
                continue;
            }
            $s = $rows->first();
            $out[$stage] = $row($s, (int) ($s->i ?? 0), (int) ($s->p ?? 0), $supplied);
            $supplied = $out[$stage]['pass'];
        }

        return $out;
    }

    /** One part of a part-wise stage (zeros when the part hasn't been sent yet). */
    public function partRow(array $summary, string $stage, string $part): array
    {
        $cutPass = (int) ($summary['cutting']['pass'] ?? 0);

        return $summary[$stage]['parts'][$part]
            ?? ['input' => 0, 'pass' => 0, 'rework' => 0, 'reject' => 0, 'wip' => 0, 'out' => 0,
                'available' => $this->partAvailable($cutPass, (int) ($summary['sewing']['input'] ?? 0), 0, 0)];
    }

    /**
     * Pieces of a part that can still go to embroidery: in the cutting balance
     * for that part (cut − sewn − still out), and never more than the cut
     * pieces not yet sent at all.
     */
    private function partAvailable(int $cutPass, int $sewn, int $sent, int $out): int
    {
        return max(0, min($cutPass - $sent, $cutPass - $sewn - $out));
    }

    /** Cutting balance: cut pieces at cutting now (for one part: that part's pieces not away at embroidery). */
    public function cuttingBalance(array $summary, ?string $part = null): int
    {
        $cutPass = (int) ($summary['cutting']['pass'] ?? 0);
        $sewn = (int) ($summary['sewing']['input'] ?? 0);
        $out = ! isset($summary['embroidery']) ? 0
            : ($part !== null ? $this->partRow($summary, 'embroidery', $part)['out'] : (int) $summary['embroidery']['out']);

        return max(0, $cutPass - $sewn - $out);
    }

    /**
     * Errors (field => message) for an entry of $qty at $stage — input can't
     * exceed what the previous stage passed, pass + reject can't exceed what
     * is in the stage.
     *
     * @param array{input_qty:int, pass_qty:int, rework_qty:int, reject_qty:int} $qty
     */
    public function validate(OrderPo $po, string $stage, array $qty, ?int $ignoreEntryId = null, ?string $part = null, ?int $sizeId = null): array
    {
        if (! in_array($stage, $this->route($po), true)) {
            $what = $stage === 'embroidery' ? 'embroidery' : 'washing';

            return ['order_po_id' => "This PO has no {$what} — tick it on the order PO if it needs one."];
        }

        // The size's own balance first (size-wise flow), then the PO's total (covers older entries without a size).
        $errors = $sizeId ? $this->checkRow($po, $stage, $qty, $this->summary($po, $ignoreEntryId, $sizeId), $part, ' of size ' . (Size::find($sizeId)->name ?? '')) : [];

        return $errors + $this->checkRow($po, $stage, $qty, $this->summary($po, $ignoreEntryId), $part, '');
    }

    private function checkRow(OrderPo $po, string $stage, array $qty, array $summary, ?string $part, string $of): array
    {
        $row = in_array($stage, self::PART_STAGES, true) ? $this->partRow($summary, $stage, (string) $part) : $summary[$stage];
        $what = self::label($stage) . ($part ? " ({$part})" : '');
        $prev = self::label($this->previous($po, $stage));
        $errors = [];

        if ($qty['input_qty'] > $row['available']) {
            // Embroidery and sewing take from the cutting balance (cut − away at embroidery − sewn).
            $errors['input_qty'] = in_array($stage, self::PART_STAGES, true) || ($stage === 'sewing' && isset($summary['embroidery']))
                ? "Only {$row['available']} pcs{$of} can come in to {$what} from the cutting balance."
                : "Only {$row['available']} pcs{$of} can come in to {$what} — {$prev} has passed " . ($row['available'] + $row['input']) . ", {$row['input']} already taken in.";
        }

        $inStage = $row['wip'] + min($qty['input_qty'], $row['available']);
        if ($qty['pass_qty'] + $qty['reject_qty'] > $inStage) {
            $errors['pass_qty'] = 'Pass + reject is ' . ($qty['pass_qty'] + $qty['reject_qty']) . " pcs but only {$inStage} pcs{$of} are in {$what}.";
        }

        if ($qty['input_qty'] + $qty['pass_qty'] + $qty['rework_qty'] + $qty['reject_qty'] === 0) {
            $errors['input_qty'] = 'Enter at least one quantity.';
        }

        return $errors;
    }

    /** The stage after $stage on the PO's route (null for the last one). */
    public function next(OrderPo $po, string $stage): ?string
    {
        $route = $this->route($po);

        return $route[array_search($stage, $route, true) + 1] ?? null;
    }

    /**
     * Passed pieces still at $stage — the next stage hasn't taken them in yet.
     * Part-wise: cutting minus what sewing and embroidery (that part) took; an
     * embroidery part's pass minus what sewing took.
     */
    public function ready(OrderPo $po, string $stage, ?array $summary = null, ?string $part = null): int
    {
        $summary ??= $this->summary($po);
        $next = $this->next($po, $stage);
        $sewn = (int) ($summary['sewing']['input'] ?? 0);

        if ($stage === 'cutting') {
            return $this->cuttingBalance($summary, $part);
        }
        if (in_array($stage, self::PART_STAGES, true) && $part !== null) {
            return max(0, $this->partRow($summary, $stage, $part)['pass'] - $sewn);
        }

        return max(0, $summary[$stage]['pass'] - ($next ? $summary[$next]['input'] : 0));
    }

    /** Why an entry can't be deleted (its passed pieces already moved on), or null. */
    public function deleteBlocked(Entry $entry): ?string
    {
        // The entry's size, then the PO total.
        foreach (array_unique([$entry->size_id, null]) as $sizeId) {
            if ($error = $this->deleteBlockedFor($entry, $sizeId)) {
                return $error;
            }
        }

        return null;
    }

    private function deleteBlockedFor(Entry $entry, ?int $sizeId): ?string
    {
        $po = $entry->orderPo;
        $after = $this->summary($po, $entry->id, $sizeId);
        $row = in_array($entry->stage, self::PART_STAGES, true) ? $this->partRow($after, $entry->stage, (string) $entry->part_name) : $after[$entry->stage];
        // Later entries of this stage passed / rejected pieces this one brought in (e.g. rework after a QC).
        if ($row['pass'] + $row['reject'] + $row['wip'] > $row['input']) {
            return 'Later ' . self::label($entry->stage) . ' entries (e.g. rework after QC) depend on this one — delete them first.';
        }
        // Cutting and embroidery share the cutting balance: what left it can't exceed what was cut.
        if ($entry->stage === 'cutting' || in_array($entry->stage, self::PART_STAGES, true)) {
            if ($this->takenFromCutting($after) > (int) $after['cutting']['pass']) {
                return 'Sewing has already taken these pieces from cutting — delete its input first.';
            }

            return null;
        }
        $next = $this->next($po, $entry->stage);
        if (($next ? $after[$next]['input'] : 0) > $after[$entry->stage]['pass']) {
            return self::label($next) . ' has already taken in these pieces — delete its entries first.';
        }

        return null;
    }

    /** What has left cutting: sewing's input plus parts still away at embroidery. */
    private function takenFromCutting(array $summary): int
    {
        return (int) ($summary['sewing']['input'] ?? 0) + (int) ($summary['embroidery']['out'] ?? 0);
    }

    /**
     * Status rows for many POs (Production Status page): po_id => summary.
     *
     * @param Collection<int, OrderPo> $pos
     * @return array<int, array<string, array<string, int>>>
     */
    public function summaries(Collection $pos): array
    {
        return $pos->mapWithKeys(fn (OrderPo $po) => [$po->id => $this->summary($po)])->all();
    }

    /**
     * Reject / rework counts must be explained: defect rows of that type add
     * up to the count. Part and machine are optional (machine = for machine-wise rejection).
     */
    public function defectErrors($defects, array $qty): array
    {
        $errors = [];
        foreach (['reject' => 'reject_qty', 'rework' => 'rework_qty'] as $type => $field) {
            $rows = $defects->where('type', $type);
            $sum = (int) $rows->sum(fn ($d) => (int) $d['qty']);
            if ($qty[$field] > 0 && $sum !== $qty[$field]) {
                $errors['defects'] = ucfirst($type) . " is {$qty[$field]} pcs but the {$type} rows add up to {$sum} — break it down below.";
            } elseif ($qty[$field] === 0 && $sum > 0) {
                $errors['defects'] = "There are {$type} rows but the {$type} quantity is 0.";
            }
        }
        return $errors;
    }

    /**
     * Entry-form balances of one stage for a PO, per size of the PO:
     * size_id => stage row (+ 'parts' / 'newPart' for part-wise stages) and 'ready' (passed, not moved on).
     */
    public function sizeBalances(OrderPo $po, string $stage): array
    {
        $partWise = in_array($stage, self::PART_STAGES, true);

        return $po->sizes->mapWithKeys(function ($s) use ($po, $stage, $partWise) {
            $summary = $this->summary($po, null, $s->size_id);
            $row = $summary[$stage] + ['ready' => $this->ready($po, $stage, $summary)];
            if ($partWise) {
                $row['newPart'] = $this->partRow($summary, $stage, '');
            }

            return [$s->size_id => $row];
        })->all();
    }

    /** po_id => part names cut for it (Cutting → Parts Cut), for part-wise entries. */
    public static function poParts($poIds): array
    {
        return DB::table('msfl_prod_cutting_parts as p')->join('msfl_prod_cuttings as c', 'c.id', '=', 'p.cutting_id')
            ->whereIn('c.order_po_id', $poIds)->whereNull('c.deleted_at')
            ->select('c.order_po_id', 'p.part_name')->distinct()->orderBy('p.part_name')->get()
            ->groupBy('order_po_id')->map(fn ($rows) => $rows->pluck('part_name')->values()->all())->all();
    }

    /** Active Inventory machines, with the line text they are on (to filter by the chosen line). */
    public static function machines()
    {
        if (! class_exists(\ME\SflInventory\Models\InvMachine::class)) {
            return collect();
        }

        return DB::table('inv_machines')->whereNull('deleted_at')->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name', 'type', 'line']);
    }
}
