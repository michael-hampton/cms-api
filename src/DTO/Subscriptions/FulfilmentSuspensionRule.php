<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\FulfilmentSuspensionDelayType;

/**
 * The resolved "how long before we suspend pending fulfilments" business
 * rule for a plan. Built by FulfilmentSuspensionPolicyResolver, consumed by
 * FulfilmentSuspensionService.
 */
final class FulfilmentSuspensionRule
{
    public function __construct(
        public readonly FulfilmentSuspensionDelayType $type,
        public readonly ?int $value = null,
    ) {
    }

    public static function immediate(): self
    {
        return new self(FulfilmentSuspensionDelayType::IMMEDIATE, null);
    }

    public static function afterDays(int $days): self
    {
        return new self(FulfilmentSuspensionDelayType::DAYS, $days);
    }

    public static function afterIssues(int $issues): self
    {
        return new self(FulfilmentSuspensionDelayType::ISSUES, $issues);
    }

    public function isImmediate(): bool
    {
        return $this->type === FulfilmentSuspensionDelayType::IMMEDIATE || (int) $this->value <= 0;
    }
}
