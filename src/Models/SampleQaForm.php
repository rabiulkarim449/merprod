<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marketing Sample QA Submission Form of one sample (client's Word form).
 * Customer, style, PO, qty, colour / size, sample type, dates, costing and
 * tech pack come from the sample itself; this row keeps only what QA types / ticks.
 */
class SampleQaForm extends Model
{
    use HasAudit;

    public const TARGET_IMPRESSION = ['premium' => 'Premium', 'technical' => 'Technical', 'sustainable' => 'Sustainable', 'value' => 'Value', 'innovation' => 'Innovation'];

    public const CUSTOMER_USE = ['meeting' => 'Meeting', 'showroom' => 'Showroom', 'exhibition' => 'Exhibition', 'photo_video' => 'Photo/video'];

    public const RANGE_COORDINATION = ['collection' => 'Matches collection', 'color' => 'Color story', 'fabric' => 'Fabric story'];

    public const QA_STATUS = ['ready' => 'Ready', 'conditional' => 'Conditional', 'hold' => 'Hold / Improve'];

    public const OWNERSHIP = ['returnable' => 'Returnable', 'retain' => 'Customer retain', 'gift' => 'Gift / display'];

    public const SUPPORT_CHECKS = [
        'design_ref' => 'Design / tech reference',
        'fabric_trim' => 'Fabric and trim identity',
        'costing' => 'Costing / indicative FOB',
        'moq' => 'MOQ and capacity',
        'sample_lead' => 'Sample lead time',
        'production_lead' => 'Production lead time',
        'compliance' => 'Compliance certificates',
        'presentation' => 'Company / product presentation',
    ];

    public const RESULTS = ['pass' => 'Pass', 'fail' => 'Fail', 'na' => 'N/A'];

    public const DEFECT_CLASSES = ['critical' => 'Critical', 'major' => 'Major', 'minor' => 'Minor'];

    /** No. => [Checkpoint, Requirement / reference] — printed on the form as given. */
    public const CHECKPOINTS = [
        1 => ['Design identity', 'Silhouette, styling, color, fabric, wash, artwork and features match the intended story.'],
        2 => ['First visual impression', 'Premium, clean, balanced and appropriate for the target customer and price position.'],
        3 => ['Fit and proportion', 'Key measurements and overall fit are credible for display, fitting or photography purpose.'],
        4 => ['Construction quality', 'Seams, stitches, shaping, matching, pocketing, lining and internal finishing are presentation-ready.'],
        5 => ['Symmetry and alignment', 'Collar/lapel, placket, pockets, checks/stripes, print/embroidery and hems align correctly.'],
        6 => ['Fabric and shade', 'No stains, holes, shade variation, face-side error, bow/skew or unacceptable fabric defect.'],
        7 => ['Trims and branding', 'Buttons, zipper, hardware, labels, badge and trims are correct, secure, functional and premium.'],
        8 => ['Print / embroidery / embellishment', 'Placement, size, color, density, registration, backing and finish support the design concept.'],
        9 => ['Pressing and finishing', 'No shine, scorching, seam impression, puckering, loose thread, chalk, odor or contamination.'],
        10 => ['Function and comfort', 'Closures, pockets, vents, adjusters and mobility function as intended; no sharp/unsafe parts.'],
        11 => ['Care / content / origin information', 'Information is accurate or clearly marked as provisional; no misleading customer claim.'],
        12 => ['Customer presentation', 'Correct hanger/fold, protective packing, sample tag and story/costing materials are complete.'],
    ];

    /** Signature boxes of the Final Release Decision. */
    public const SIGNATORIES = ['Quality Controller', 'QC Manager', 'Sample Incharge', 'Production Manager'];

    protected $table = 'msfl_sample_qa_forms';

    protected $fillable = [
        'sample_id', 'brand_market', 'marketing_purpose', 'meeting_event', 'qa_inspection_date', 'custodian', 'return_due_date',
        'target_impression', 'customer_use', 'range_coordination', 'key_selling_story', 'qa_status', 'ownership',
        'support_checks', 'checkpoints', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'qa_inspection_date' => 'date',
        'return_due_date' => 'date',
        'target_impression' => 'array',
        'customer_use' => 'array',
        'range_coordination' => 'array',
        'support_checks' => 'array',
        'checkpoints' => 'array',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }
}
