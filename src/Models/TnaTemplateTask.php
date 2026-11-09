<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step of a T&A template: when it falls (anchor ± working days) and where its actual date can come from. */
class TnaTemplateTask extends Model
{
    public const ANCHORS = [
        'order_confirm' => 'Order Confirmation',
        'pcd' => 'PCD (Cutting Start)',
        'sewing_start' => 'Sewing Start',
        'sewing_end' => 'Sewing End',
        'ex_factory' => 'Ex-Factory',
        'shipment' => 'Shipment',
    ];

    public const CONDITIONS = ['wash' => 'Only if the style has a wash'];

    protected $table = 'msfl_tna_template_tasks';

    protected $fillable = ['tna_template_id', 'sequence', 'group_name', 'task_code', 'task_name', 'anchor', 'offset_days', 'auto_source', 'condition', 'is_mandatory'];

    protected $casts = ['is_mandatory' => 'boolean'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TnaTemplate::class, 'tna_template_id');
    }

    /** Auto-source options: fixed ones + one submitted / approved pair per sample type. */
    public static function autoSources(): array
    {
        $sources = [
            'none' => 'Manual',
            'order_confirmed' => 'Order confirmed',
            'bom_approved' => 'BOM approved',
            'bulletin_approved' => 'Bulletin approved',
            // From Inventory / Production (same system — filled as the work happens).
            'fabric_inhouse' => 'Fabric received in Buyer Store (first GRN)',
            'cutting_started' => 'Cutting started (first cutting)',
            'sewing_started' => 'Sewing started (first sewing input)',
            'sewing_done' => 'Sewing complete (sewing pass reaches order qty)',
            'washing_done' => 'Washing complete (washing pass reaches order qty)',
            'finishing_done' => 'Finishing complete (finishing pass reaches order qty)',
            'final_qc_done' => 'Buyer QC complete (buyer QC pass reaches order qty)',
            'packing_done' => 'Packing complete (packed reaches order qty)',
        ];

        foreach (SampleType::query()->active()->orderBy('sequence')->get() as $type) {
            $sources['sample_submitted:' . $type->code] = $type->name . ' — submitted';
            $sources['sample_approved:' . $type->code] = $type->name . ' — approved';
        }

        return $sources;
    }

    /** "PCD −10" style rule text. */
    public static function ruleText(string $anchor, int $offset): string
    {
        $label = self::ANCHORS[$anchor] ?? $anchor;

        return $offset === 0 ? $label : $label . ' ' . ($offset > 0 ? '+' : '−') . abs($offset) . 'd';
    }
}
