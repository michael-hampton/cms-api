<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Contracts;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\PolicyValidationResult;
use App\Enums\Subscriptions\ReplacementLimitScope;

/**
 * Contract implemented by every replacement policy strategy.
 *
 * Beyond validate()/evaluate() (the two methods the ticket specifies),
 * this also declares id()/name()/replacementLimitScope()/extensionLimitScope().
 * Those four are a pragmatic addition, not part of the ticket's snippet:
 *   - id()/name() are needed because IssueResolutionService persists and
 *     logs the resolved policy (replacement_policy_id, policy name in
 *     logs) — the same way it did against the old ReplacementPolicy model.
 *   - replacementLimitScope()/extensionLimitScope() are needed because the
 *     orchestrator has to know *what to count* (per issue / per
 *     subscription / per year / lifetime) before it can build the
 *     ReplacementUsageStatistics that go into PolicyContext — the ticket's
 *     PolicyContext is supposed to arrive pre-populated with usage data,
 *     so something upstream of evaluate() has to know the scope first.
 *
 * If your team intends the interface to stay literally two methods, these
 * four should move onto a separate, smaller interface — flagging that as
 * a design choice worth confirming rather than deciding unilaterally.
 */
interface ReplacementPolicyInterface
{
    public function id(): int;

    public function name(): string;

    public function validate(PolicyContext $context): PolicyValidationResult;

    public function evaluate(PolicyContext $context): PolicyEvaluationResult;

    public function replacementLimitScope(): ReplacementLimitScope;

    public function extensionLimitScope(): ReplacementLimitScope;
}
