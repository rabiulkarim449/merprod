<?php

namespace ME\MerchandisingSfl\Approvals;

use App\Approvals\BaseApprovalHandler;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use ME\MerchandisingSfl\Services\SampleDecision;

/** 'msfl.sample' — recording the buyer decision on a submitted sample from the central Approvals page. */
class SampleApprovalHandler extends BaseApprovalHandler
{
    public function recipients(?Model $approvable, Approval $approval): array
    {
        return User::all()->filter(fn (User $u) => method_exists($u, 'hasPermission') && $u->hasPermission('msfl_sample.approve'))
            ->pluck('email')->filter()->values()->all();
    }

    public function onApproved(Approval $approval): void
    {
        $this->apply($approval, 'approved');
    }

    public function onRejected(Approval $approval): void
    {
        $this->apply($approval, 'rejected');
    }

    private function apply(Approval $approval, string $decision): void
    {
        $sample = $approval->approvable;
        if ($sample && $sample->status === 'submitted') {
            // A rejection from the central page also opens the next revision, like the sample page's default.
            app(SampleDecision::class)->decide($sample, $decision, today(), $approval->remarks, $decision === 'rejected', $approval->approved_by);
        }
    }

    public function mailContent(?Model $approvable, Approval $approval): array
    {
        if (! $approvable) {
            return [];
        }
        $approvable->loadMissing(['style', 'buyer', 'sampleType']);

        return ['badge' => 'SAMPLE APPROVAL', 'number' => $approvable->sample_no, 'meta' => array_filter([
            'Type' => $approvable->sampleType->name ?? null, 'Style' => $approvable->style->style_no ?? null, 'Buyer' => $approvable->buyer->name ?? null,
            'Qty' => $approvable->qty, 'Submitted' => $approvable->submit_date?->format('d-M-Y'), 'Courier' => trim($approvable->courier_name . ' ' . $approvable->tracking_no) ?: null,
        ])];
    }
}
