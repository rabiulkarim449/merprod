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
 * Pre-order costing. Every cost column is per dozen; per-piece figures
 * (offer / target / final price) are per piece. recalculate() is the only
 * place the totals are derived.
 */
class CostSheet extends Model
{
    use HasAudit;
    use HasStatus;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => ['Draft', 'secondary'],
        'approved' => ['Approved', 'success'],
    ];

    /** Open Cost Sheet sections: key => [letter, title, total label]. Lines are per dozen. */
    public const GROUPS = [
        'fabric' => ['A', 'Shell / Body Fabrics', 'Total Fabric Cost'],
        'trims' => ['B', 'Accessories Details', 'Total Trims Cost'],
        'wash' => ['C', 'Wash', 'Total Wash Cost'],
        'stone' => ['D', 'Stone', 'Stone Cost'],
        'print' => ['E', 'Print', 'GMT Print Cost'],
        'heat_seal' => ['F', 'Heat Seal Charge', 'H/Seal Charge'],
        'process' => ['G', 'Embroidery / Other Process', 'Emb / Other Process Cost'],
    ];

    /** Stored per-dozen column each section adds to (post costing reads fabric / trims / process). */
    public const GROUP_COLUMNS = [
        'fabric' => 'fabric_cost', 'trims' => 'trims_cost',
        'wash' => 'process_cost', 'stone' => 'process_cost', 'print' => 'process_cost', 'heat_seal' => 'process_cost', 'process' => 'process_cost',
    ];

    public const PRICE_TYPES = ['FOB' => 'FOB', 'CFR' => 'CFR', 'CIF' => 'CIF', 'CMT' => 'CMT', 'DDP' => 'DDP', 'EXW' => 'EXW'];

    protected $table = 'msfl_cost_sheets';

    protected $fillable = [
        'cost_sheet_no', 'buyer_id', 'style_id', 'inquiry_id', 'style_ref', 'garment_description', 'size_range',
        'currency_id', 'price_type', 'costing_date', 'order_qty', 'smv', 'cm_minute_rate', 'efficiency_percent', 'cm_cost', 'commercial_percent', 'other_cost',
        'profit_percent', 'buyer_target_price', 'final_price', 'remarks', 'created_by',
    ];

    protected $casts = [
        'costing_date' => 'date',
        'approved_at' => 'datetime',
        'smv' => 'decimal:2',
        'cm_minute_rate' => 'decimal:4',
        'efficiency_percent' => 'decimal:2',
        'fabric_cost' => 'decimal:4',
        'trims_cost' => 'decimal:4',
        'process_cost' => 'decimal:4',
        'cm_cost' => 'decimal:4',
        'commercial_percent' => 'decimal:2',
        'commercial_cost' => 'decimal:4',
        'other_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'profit_percent' => 'decimal:2',
        'profit_amount' => 'decimal:4',
        'offer_price' => 'decimal:4',
        'buyer_target_price' => 'decimal:4',
        'final_price' => 'decimal:4',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CostSheetItem::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function materialCost(): float
    {
        return (float) $this->fabric_cost + (float) $this->trims_cost + (float) $this->process_cost;
    }

    /**
     * Per dozen:
     *   material   = fabric + trims + process (sum of line amounts)
     *   commercial = material x commercial %
     *   total      = material + CM + commercial + other
     *   profit     = total x profit %
     * Per piece:
     *   offer      = (total + profit) / 12
     */
    public function recalculate(): void
    {
        $this->loadMissing('items');

        foreach (array_unique(self::GROUP_COLUMNS) as $column) {
            $this->{$column} = 0;
        }
        foreach (self::GROUP_COLUMNS as $group => $column) {
            $this->{$column} = round((float) $this->{$column} + $this->items->where('group', $group)->sum('amount'), 4);
        }

        $material = $this->materialCost();
        $this->commercial_cost = round($material * (float) $this->commercial_percent / 100, 4);
        $this->total_cost = round($material + (float) $this->cm_cost + (float) $this->commercial_cost + (float) $this->other_cost, 4);
        $this->profit_amount = round((float) $this->total_cost * (float) $this->profit_percent / 100, 4);
        $this->offer_price = round(((float) $this->total_cost + (float) $this->profit_amount) / 12, 4);

        $this->save();
    }

    /** CM per dozen from SMV, cost per minute and efficiency (null when not enough data). */
    public function cmFromMinutes(): ?float
    {
        $smv = (float) $this->smv;
        $cpm = (float) $this->cm_minute_rate;
        $eff = (float) $this->efficiency_percent ?: 100;

        return $smv > 0 && $cpm > 0 ? round($smv / ($eff / 100) * $cpm * 12, 4) : null;
    }

    /**
     * Figures of the Open Cost Sheet (screen, print): each section, materials, CM,
     * commercial, other, profit and FOB — per dozen, per piece and % of FOB — plus the
     * per-piece strip (main fabric = first fabric line; pocketing / fusing split out by name).
     * Same math as recalculate().
     */
    public function summary(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $groups = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $groups[$group] = (float) $items->where('group', $group)->sum('amount');
        }

        $material = array_sum($groups);
        $cm = (float) $this->cm_cost;
        $commercial = $material * (float) $this->commercial_percent / 100;
        $other = (float) $this->other_cost;
        $total = $material + $cm + $commercial + $other;
        $profit = $total * (float) $this->profit_percent / 100;
        $fob = $total + $profit;
        $row = fn (float $dz) => ['dz' => $dz, 'pc' => $dz / 12, 'pct' => $fob > 0 ? $dz / $fob * 100 : 0.0];

        $fabricLines = $items->where('group', 'fabric')->values();
        $main = $fabricLines->first();
        $pick = fn (string $pattern) => (float) $fabricLines->filter(fn ($l) => preg_match($pattern, $l->label()))->sum('amount');
        $pocket = $pick('/pocket|pkt/i');
        $fusing = $pick('/fus|interlin/i');

        return [
            'groups' => array_map($row, $groups),
            'materials' => $row($material),
            'cm' => $row($cm),
            'sub_total' => $row($material + $cm),
            'commercial' => $row($commercial) + ['rate' => (float) $this->commercial_percent],
            'other' => $row($other),
            'profit' => $row($profit) + ['rate' => (float) $this->profit_percent],
            'fob' => $row($fob),
            'b2b_pct' => $fob > 0 ? $material / $fob * 100 : 0.0,
            'strip' => [
                'fabric_price' => $main ? (float) $main->rate : null,
                'fabric_consumption_pc' => $main ? (float) $main->consumption / 12 : null,
                'fabric_uom' => $main?->uom?->code,
                'fabric_cost_pc' => ($groups['fabric'] - $pocket - $fusing) / 12,
                'pocket_pc' => $pocket / 12,
                'fusing_pc' => $fusing / 12,
            ],
        ];
    }

    /** Style no shown on the sheet: the style's, else the typed ref. */
    public function styleLabel(): string
    {
        return $this->style->style_no ?? ($this->style_ref ?? '');
    }
}
