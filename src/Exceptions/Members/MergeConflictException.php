<?php

declare(strict_types=1);

namespace App\Exceptions\Members;

use RuntimeException;

/**
 * Thrown by MemberMergeService when a merge cannot proceed due to conflicts
 * that require manual admin resolution.
 *
 * The $conflicts array is structured so the frontend can render each
 * conflict specifically rather than showing a generic error message.
 *
 * Shape:
 * [
 *   ['code' => 'active_subscriptions',      'message' => 'Both members have active subscriptions.'],
 *   ['code' => 'conflicting_stripe_customers','message' => 'Both members have different active Stripe customer IDs.'],
 *   ...
 * ]
 */
final class MergeConflictException extends RuntimeException
{
    /** @param array<array{code: string, message: string}> $conflicts */
    public function __construct(
        private readonly array $conflicts,
        string $message = 'Merge blocked due to unresolved conflicts.',
    ) {
        parent::__construct($message);
    }

    /** @return array<array{code: string, message: string}> */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }
}