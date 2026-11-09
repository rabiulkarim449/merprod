<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ME\MerchandisingSfl\Models\Buyer;
use ME\MerchandisingSfl\Models\Style;

/**
 * Pulls Buyers and Styles that already exist in Inventory (entered there
 * first, before this Merchandising side was used) into Merchandising's own
 * masters — the reverse direction of sfl-inventory's MerchandisingLink
 * (which mirrors a Merchandising buyer into Inventory). Deliberately plain
 * DB queries against Inventory's tables, not its Eloquent models — Inventory
 * already depends on Merchandising (MerchandisingLink), so depending back on
 * Inventory's classes here would make the two packages depend on each
 * other. A no-op wherever Inventory's tables don't exist.
 */
class InventorySync
{
    public function available(): bool
    {
        return Schema::hasTable('inv_buyers');
    }

    /** Inventory buyers with no matching Merchandising buyer yet (matched by name, case/space-insensitive). */
    public function pendingBuyers(): Collection
    {
        if (! $this->available()) {
            return collect();
        }

        $known = Buyer::query()->get(['name'])->map(fn ($b) => mb_strtolower(trim($b->name)))->flip();

        return DB::table('inv_buyers')->whereNull('deleted_at')
            ->get(['id', 'code', 'name', 'address', 'contact'])
            ->reject(fn ($b) => isset($known[mb_strtolower(trim($b->name))]))
            ->values();
    }

    /**
     * Style text Inventory already knows (posted buyer-supplied GRNs, or FG
     * Receives not yet linked to a real Merchandising style) for a buyer
     * that has — or is about to get, via syncBuyers() — a Merchandising
     * match, where that style text isn't a real Merchandising style yet.
     */
    public function pendingStyles(): Collection
    {
        if (! $this->available()) {
            return collect();
        }

        $invBuyers = DB::table('inv_buyers')->whereNull('deleted_at')->pluck('name', 'id');
        $merBuyerByName = Buyer::query()->get(['id', 'name'])->mapWithKeys(fn ($b) => [mb_strtolower(trim($b->name)) => $b->id]);
        $invToMer = $invBuyers->mapWithKeys(fn ($name, $id) => [$id => $merBuyerByName->get(mb_strtolower(trim($name)))])->filter();

        if ($invToMer->isEmpty()) {
            return collect();
        }

        $known = Style::query()->get(['buyer_id', 'style_no'])
            ->mapWithKeys(fn ($s) => [$s->buyer_id . '|' . mb_strtolower(trim($s->style_no)) => true]);

        $grnStyles = Schema::hasTable('inv_grns') ? DB::table('inv_grns')
            ->where('source_type', 'buyer_supplied')->where('status', 'posted')
            ->whereIn('buyer_id', $invToMer->keys())->whereNull('deleted_at')
            ->whereNotNull('style')->where('style', '<>', '')
            ->distinct()->get(['buyer_id', 'style']) : collect();

        $fgStyles = Schema::hasTable('inv_finished_goods_receives') ? DB::table('inv_finished_goods_receives')
            ->whereIn('buyer_id', $invToMer->keys())->whereNull('deleted_at')
            ->whereNull('msfl_style_id')->whereNotNull('style')->where('style', '<>', '')
            ->distinct()->get(['buyer_id', 'style']) : collect();

        return $grnStyles->concat($fgStyles)
            ->map(fn ($r) => (object) ['style_no' => trim($r->style), 'buyer_id' => $invToMer[$r->buyer_id]])
            ->reject(fn ($s) => isset($known[$s->buyer_id . '|' . mb_strtolower($s->style_no)]))
            ->unique(fn ($s) => $s->buyer_id . '|' . mb_strtolower($s->style_no))
            ->sortBy('style_no', SORT_NATURAL)
            ->values();
    }

    /**
     * Creates a Merchandising Buyer — auto-approved, since it's already a
     * real, in-use buyer in Inventory, not a new one needing vetting — for
     * every pending Inventory buyer. Returns the names actually created.
     */
    public function syncBuyers(): array
    {
        $created = [];

        foreach ($this->pendingBuyers() as $invBuyer) {
            $buyer = new Buyer([
                'code' => $this->uniqueBuyerCode($invBuyer->code ?: $invBuyer->name),
                'name' => $invBuyer->name,
                'address' => $invBuyer->address,
                'contact_person' => $invBuyer->contact,
                'is_active' => true,
            ]);
            $buyer->created_by = auth()->id();
            $buyer->forceFill(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $buyer->save();
            $created[] = $buyer->name;
        }

        return $created;
    }

    /**
     * Creates a Merchandising Style for every pending Inventory style-text
     * row. style_no is unique per buyer (not globally) — two different
     * buyers reusing the same style text is expected and fine; pendingStyles()
     * already excludes anything that would collide for the SAME buyer. A row
     * is only skipped if something else (e.g. a concurrent sync) creates that
     * exact buyer+style_no between the preview and this running.
     * Returns ['created' => [...], 'skipped' => [...]].
     */
    public function syncStyles(): array
    {
        $created = [];
        $skipped = [];

        foreach ($this->pendingStyles() as $row) {
            if (Style::where('buyer_id', $row->buyer_id)->where('style_no', $row->style_no)->exists()) {
                $skipped[] = $row->style_no;

                continue;
            }

            Style::create([
                'style_no' => $row->style_no,
                'name' => $row->style_no,
                'buyer_id' => $row->buyer_id,
                'development_status' => 'new',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
            $created[] = $row->style_no;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function uniqueBuyerCode(string $base): string
    {
        $base = Str::upper(Str::slug($base, '')) ?: 'BUYER';
        $code = $base;
        for ($n = 2; Buyer::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
    }
}
