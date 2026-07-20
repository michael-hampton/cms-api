<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions\BusinessDecisions;

final class ResolvedRefundOptions
{
    public function __construct(
        public readonly bool $allowFull,
        public readonly bool $allowProRated,
        public readonly bool $allowManual,
        public readonly bool $allowCancelAtPeriodEnd,
        public readonly bool $allowCancelImmediatelyNoRefund,
        public readonly int $refundMaxPercent,
        public readonly ?int $managerApprovalThresholdPercent,
        public readonly bool $defaultNotifyCustomer,
        public readonly bool $requiresInternalNotes,
    ) {
    }

    public function toArray(): array
    {
        return [
            'allow_full' => $this->allowFull,
            'allow_pro_rated' => $this->allowProRated,
            'allow_manual' => $this->allowManual,
            'allow_cancel_at_period_end' => $this->allowCancelAtPeriodEnd,
            'allow_cancel_immediately_no_refund' => $this->allowCancelImmediatelyNoRefund,
            'refund_max_percent' => $this->refundMaxPercent,
            'manager_approval_threshold_percent' => $this->managerApprovalThresholdPercent,
            'default_notify_customer' => $this->defaultNotifyCustomer,
            'requires_internal_notes' => $this->requiresInternalNotes,
        ];
    }
}
