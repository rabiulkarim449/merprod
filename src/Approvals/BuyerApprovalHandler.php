<?php

namespace ME\MerchandisingSfl\Approvals;

use App\Approvals\BaseApprovalHandler;
use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** 'msfl.buyer' on the host's central Approvals page — approvers: users with msfl_buyer.approve. */
class BuyerApprovalHandler extends BaseApprovalHandler
{
    public function recipients(?Model $approvable, Approval $approval): array
    {
        return User::all()->filter(fn (User $u) => method_exists($u, 'hasPermission') && $u->hasPermission('msfl_buyer.approve'))
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

    private function apply(Approval $approval, string $status): void
    {
        $buyer = $approval->approvable;
        if ($buyer && method_exists($buyer, 'isPendingApproval') && $buyer->isPendingApproval()) {
            $buyer->applyApprovalDecision($status, $approval->approved_by, $approval->approved_at, $approval->remarks);
        }
    }

    public function mailContent(?Model $approvable, Approval $approval): array
    {
        if (! $approvable) {
            return [];
        }

        return ['badge' => 'NEW BUYER', 'number' => $approvable->code, 'meta' => array_filter([
            'Code' => $approvable->code, 'Name' => $approvable->name, 'Country' => $approvable->country,
            'Agent' => $approvable->agent_name, 'Contact Person' => $approvable->contact_person, 'Email' => $approvable->email,
        ])];
    }
}
