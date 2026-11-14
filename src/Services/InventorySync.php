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

    /** Store categories a BOM uses (fabric, trims, accessories, labels, packing, thread) — ticked by default on the sync page. */
    public const DEFAULT_ITEM_CATEGORY_WORDS = ['fabric', 'jacq', 'trim', 'button', 'accessor', 'label', 'decor', 'packag', 'packing', 'yarn', 'thread'];

    /** Inventory item categories with how many of their items are not in Merchandising yet: [id => {name, pending, default}]. */
    public function itemCategories(): Collection
    {
        if (! Schema::hasTable('inv_items')) {
            return collect();
        }
        $pending = $this->pendingItemsQuery()->selectRaw('i.category_id, count(*) as n')->groupBy('i.category_id')->pluck('n', 'category_id');

        return DB::table('inv_item_categories')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => (object) [
                'id' => $c->id, 'name' => $c->name, 'pending' => (int) ($pending[$c->id] ?? 0),
                'default' => Str::contains(mb_strtolower($c->name), self::DEFAULT_ITEM_CATEGORY_WORDS),
            ])
            ->filter(fn ($c) => $c->pending > 0)->values();
    }

    /** Store items of these categories that no live Merchandising item is linked to yet. */
    public function pendingItems(array $categoryIds): Collection
    {
        if (! Schema::hasTable('inv_items') || ! $categoryIds) {
            return collect();
        }

        return $this->pendingItemsQuery()->whereIn('i.category_id', $categoryIds)
            ->leftJoin('inv_item_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('inv_units as u', 'u.id', '=', 'i.unit_id')
            ->orderBy('c.name')->orderBy('i.item_code')
            ->get(['i.id', 'i.item_code', 'i.item_name', 'i.category_id', 'i.unit_id', 'i.supplier_id', 'i.specification', 'c.name as category', 'u.name as unit']);
    }

    /**
     * Creates a Merchandising item for every pending store item of the chosen categories, linked by
     * inv_item_id (code, name, unit, supplier and a same-named v2 category copied). A live v2 item
     * with the same code and no link yet is linked instead of duplicated.
     * Returns ['created' => n, 'linked' => n].
     */
    public function syncItems(array $categoryIds): array
    {
        $created = $linked = 0;
        $categories = [];

        foreach ($this->pendingItems($categoryIds) as $inv) {
            $existing = \ME\MerchandisingSfl\Models\Item::query()->where('code', $inv->item_code)->whereNull('inv_item_id')->first();
            if ($existing) {
                $existing->update(['inv_item_id' => $inv->id]);
                $linked++;

                continue;
            }

            $type = $this->itemType((string) $inv->category);
            $categories[$inv->category_id] ??= $this->v2Category((string) $inv->category, $type);

            \ME\MerchandisingSfl\Models\Item::create([
                'code' => $this->uniqueItemCode($inv->item_code ?: 'ITEM-' . $inv->id),
                'inv_item_id' => $inv->id,
                'name' => $inv->item_name,
                'type' => $type,
                'category_id' => $categories[$inv->category_id],
                'uom_id' => $inv->unit_id,
                'default_supplier_id' => $inv->supplier_id,
                'description' => $inv->specification,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);
            $created++;
        }

        return ['created' => $created, 'linked' => $linked];
    }

    private function pendingItemsQuery()
    {
        $linked = DB::table('msfl_items')->whereNull('deleted_at')->whereNotNull('inv_item_id')->select('inv_item_id');

        return DB::table('inv_items as i')->whereNull('i.deleted_at')->where('i.item_type', '<>', 'finished_good')
            ->whereNotIn('i.id', $linked);
    }

    /** v2 item type from the store category name. */
    private function itemType(string $category): string
    {
        $c = mb_strtolower($category);

        return match (true) {
            Str::contains($c, ['fabric', 'jacq']) => 'fabric',
            Str::contains($c, ['packag', 'packing', 'carton', 'poly']) => 'packing',
            Str::contains($c, ['button', 'accessor', 'label', 'decor']) => 'accessories',
            default => 'trims',
        };
    }

    /** The v2 item category with this name (created when missing). */
    private function v2Category(string $name, string $type): ?int
    {
        if ($name === '') {
            return null;
        }
        $category = \ME\MerchandisingSfl\Models\ItemCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($category) {
            return $category->id;
        }

        $base = Str::upper(Str::slug($name, '-')) ?: 'CAT';
        $code = $base;
        for ($n = 2; \ME\MerchandisingSfl\Models\ItemCategory::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return \ME\MerchandisingSfl\Models\ItemCategory::create(['code' => $code, 'name' => $name, 'type' => $type, 'is_active' => true, 'created_by' => auth()->id()])->id;
    }

    private function uniqueItemCode(string $base): string
    {
        $base = Str::limit(trim($base), 45, '');
        $code = $base;
        for ($n = 2; \ME\MerchandisingSfl\Models\Item::withTrashed()->where('code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
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
