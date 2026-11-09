<?php

namespace ME\MerchandisingSfl\Services;

use App\Models\Approval;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\DB;
use ME\MerchandisingSfl\Models\Sample;

/**
 * The buyer's decision on a submitted sample — recorded on the sample page or
 * on the host's central Approvals page (module 'msfl.sample'); whichever is
 * used, the other is kept in step. A rejection can open the next revision.
 */
class SampleDecision
{
    public const MODULE = 'msfl.sample';

    public static function available(): bool
    {
        return class_exists(ApprovalService::class) && class_exists(Approval::class);
    }

    /** Raise the central request when a sample goes to the buyer (once). */
    public function requestApproval(Sample $sample): void
    {
        if (! static::available() || $this->pending($sample)) {
            return;
        }

        $sample->loadMissing(['style', 'buyer', 'sampleType']);
        app(ApprovalService::class)->request([
            'module' => self::MODULE,
            'approvable' => $sample,
            'title' => "Sample {$sample->sample_no} (" . ($sample->sampleType->name ?? '') . ') - ' . ($sample->style->style_no ?? '') . ' / ' . ($sample->buyer->name ?? ''),
            'description' => 'Submitted ' . $sample->submit_date?->format('d-M-Y') . ($sample->revision_no ? " · revision {$sample->revision_no}" : '')
                . ($sample->courier_name ? " · via {$sample->courier_name} {$sample->tracking_no}" : '') . '. Record the buyer decision: approve / reject.',
            'route_name' => 'msfl.samples.show',
            'route_params' => ['sample' => $sample->id],
            'requested_by' => auth()->id(),
        ]);
    }

    /** Apply the decision; returns the new revision when a rejection opens one. */
    public function decide(Sample $sample, string $decision, $date, ?string $comments, bool $createRevision, ?int $userId): ?Sample
    {
        return DB::transaction(function () use ($sample, $decision, $date, $comments, $createRevision, $userId) {
            $sample->forceFill(['status' => $decision, 'decision_date' => $date, 'decided_by' => $userId, 'buyer_comments' => $comments])->save();

            if ($decision !== 'rejected' || ! $createRevision) {
                return null;
            }

            $revision = $sample->replicate(['sample_no', 'status', 'submit_date', 'courier_name', 'tracking_no', 'decision_date', 'decided_by', 'buyer_comments', 'attachment']);
            $revision->forceFill([
                'sample_no' => app(DocumentNumberService::class)->next('sample', Sample::class, 'sample_no'),
                'status' => 'requested', 'revision_no' => $sample->revision_no + 1, 'parent_sample_id' => $sample->id,
                'request_date' => today(), 'required_date' => null, 'created_by' => $userId,
            ])->save();

            return $revision;
        });
    }

    /** Decided on the sample page: close the central request directly (not through its handler). */
    public function closeCentral(Sample $sample, string $status, ?string $remarks, ?int $userId): void
    {
        if (static::available()) {
            Approval::query()->pending()->where('approvable_type', Sample::class)->where('approvable_id', $sample->id)
                ->update(['status' => $status, 'approved_by' => $userId, 'approved_at' => now(), 'remarks' => $remarks]);
        }
    }

    public function pending(Sample $sample): ?Approval
    {
        return static::available()
            ? Approval::query()->pending()->where('approvable_type', Sample::class)->where('approvable_id', $sample->id)->latest('id')->first()
            : null;
    }
}
