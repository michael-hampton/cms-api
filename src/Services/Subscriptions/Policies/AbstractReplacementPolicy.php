<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyValidationResult;
use App\Models\ReplacementPolicy;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;

/**
 * Shared functionality for every concrete replacement policy: casting the
 * underlying ReplacementPolicy config row, the active-flag validation
 * every policy needs, and small helpers concrete policies can reuse.
 *
 * Deliberately contains no business rules specific to any one policy —
 * per the ticket, that belongs entirely in the concrete subclasses.
 */
abstract class AbstractReplacementPolicy implements ReplacementPolicyInterface
{
    public function __construct(
        protected readonly ReplacementPolicy $policyModel,
    ) {
    }

    public function id(): int
    {
        return (int) $this->policyModel->id;
    }

    public function name(): string
    {
        return (string) $this->policyModel->name;
    }

    /**
     * Base validation every policy shares (the row must be active).
     * Concrete policies that need additional validation should override
     * validatePolicy(), not this method.
     */
    final public function validate(PolicyContext $context): PolicyValidationResult
    {
        if (!$this->policyModel->active) {
            return PolicyValidationResult::invalid('This replacement policy is not active.');
        }

        return $this->validatePolicy($context);
    }

    protected function validatePolicy(PolicyContext $context): PolicyValidationResult
    {
        return PolicyValidationResult::valid();
    }

    /**
     * True when a usage count has met or exceeded a configured limit.
     * A null limit means unlimited.
     */
    protected function limitReached(?int $max, int $used): bool
    {
        return $max !== null && $used >= $max;
    }
}
