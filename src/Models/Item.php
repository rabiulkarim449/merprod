<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class Item extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_items';

    protected $fillable = ['code', 'inv_item_id', 'name', 'category_id', 'type', 'uom_id', 'default_supplier_id', 'default_price', 'composition', 'gsm', 'description', 'is_active', 'created_by'];

    /**
     * Inventory items for the "Inventory Item" picker — [id => "CODE — Name (Category)"]; empty when the
     * Inventory package is not installed. Plain query, no Inventory model (the packages stay independent).
     */
    public static function inventoryOptions(): array
    {
        static $options = null;
        if ($options !== null) {
            return $options;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('inv_items')) {
            return $options = [];
        }

        return $options = \Illuminate\Support\Facades\DB::table('inv_items as i')
            ->leftJoin('inv_item_categories as c', 'c.id', '=', 'i.category_id')
            ->whereNull('i.deleted_at')->where('i.item_type', '<>', 'finished_good')
            ->orderBy('i.item_code')->get(['i.id', 'i.item_code', 'i.item_name', 'c.name as category'])
            ->mapWithKeys(fn ($i) => [$i->id => trim($i->item_code . ' — ' . $i->item_name . ($i->category ? ' (' . $i->category . ')' : ''))])
            ->all();
    }

    /** "CODE — Name" of the linked store item, or null. */
    public function inventoryLabel(): ?string
    {
        return $this->inv_item_id ? (self::inventoryOptions()[$this->inv_item_id] ?? null) : null;
    }

    protected $casts = ['is_active' => 'boolean', 'default_price' => 'decimal:4', 'gsm' => 'decimal:2'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }
}
