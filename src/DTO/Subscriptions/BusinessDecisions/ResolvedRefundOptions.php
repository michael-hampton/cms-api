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

    /**
     * True when a refund requesting $requestedPercent (0-100) of the
     * original payment amount needs the
     * crm.subscriptions.refund.approve permission before it can
     * proceed. A threshold of 100 (the FIELD_DEFAULTS value) never
     * triggers this, since a refund can't exceed 100% of the payment.
     */
    public function requiresManagerApprovalFor(float $requestedPercent): bool
    {
        return $requestedPercent > $this->managerApprovalThresholdPercent;
    }

    /**
     * Whether the given SubscriptionRefundService/RefundStrategy result
     * type ('full'|'pro_rated'|'manual') is permitted for this reason.
     * cancel_at_period_end/cancel_immediately_no_refund are resolved
     * here for the read path (so the CRM UI can grey them out) but are
     * cancellation actions, not something SubscriptionRefundService
     * ever produces, so they are not checked by this method.
     */
    public function allowsType(string $type): bool
    {
        return match ($type) {
            'full' => $this->allowFull,
            'pro_rated' => $this->allowProRated,
            'manual' => $this->allowManual,
            default => true,
        };
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
