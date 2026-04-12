<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Models\Model;
use App\Models\PayoutAudit;
use App\Repositories\Repository;

class PayoutAuditRepository extends Repository
{
    public function log(
        int               $payoutId,
        PayoutAuditAction $action,
        int               $performedBy,
        ?string           $reason = null,
    ): Model
    {
        return $this->create([
            'payout_id' => $payoutId,
            'action' => $action->value,
            'performed_by' => $performedBy,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Full audit trail for a single payout, oldest first.
     */
    public function forPayout(int $payoutId): \App\Framework\Support\Collection
    {
        return PayoutAudit::where('payout_id', $payoutId)
            ->orderBy('created_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return PayoutAudit::class;
    }
}