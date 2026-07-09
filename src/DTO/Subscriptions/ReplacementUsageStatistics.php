<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

/**
 * How much of a subscription's replacement/extension entitlement has
 * already been consumed. Fed into ReplacementPolicyEvaluator so it can
 * enforce a policy's max_replacements / max_extensions limits without
 * touching a repository itself.
 */
final class ReplacementUsageStatistics
{
    public function __construct(
        public readonly int $replacementsUsed,
        public readonly int $extensionsUsed,
    ) {}
}