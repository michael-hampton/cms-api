<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Contracts;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\PolicyValidationResult;
use App\Enums\Subscriptions\PolicySettingKey;
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
 *
 * evaluateCancellation()/evaluatePause() extend this same contract for the
 * "Integrate Subscription Policies into Cancellation and Pause Workflows"
 * ticket. Per that ticket: "If separate evaluation methods are currently
 * used, introduce evaluateCancellation()/evaluatePause() or an equivalent
 * implementation that aligns with the existing framework" — evaluate()
 * here is bound to ReplacementResolution (replace/extend) and to
 * PolicyContext, which requires a non-nullable IssueDelivery that doesn't
 * exist for a cancellation or pause request, so a single unified
 * evaluate(PolicyContext) wasn't a clean fit. Kept on this same interface
 * (rather than a new one) so ReplacementPolicyResolver's existing
 * plan/site resolution is reused unchanged for all four actions, per the
 * ticket's "no new policy architecture" framing.
 *
 * NAMING NOTE: this interface/class family is still named
 * "Replacement..." even though it now also governs cancellation and
 * pause. Renaming (e.g. to SubscriptionPolicyInterface) touches every
 * concrete policy, the resolver, and IssueResolutionService — flagging as
 * a rename worth doing deliberately in its own change rather than folding
 * into this ticket's diff.
 *
 * overridableSettings() supports the "business decisions in subscriptions
 * can be overridden" ticket: each concrete policy declares which of its
 * pause/cancellation settings (PolicySettingKey cases) an admin is
 * allowed to override per site, and that setting's default value.
 * SubscriptionPolicySettingOverrideService validates admin input against
 * this list before persisting an override; PolicySettingOverrideResolver
 * feeds active overrides back into PausePolicyContext/CancellationPolicyContext
 * for evaluatePause()/evaluateCancellation() to read instead of their own
 * consts. A policy with no overridable pause/cancellation behaviour
 * (GoodwillPolicy — it's the internal fallback target for issue
 * resolution overrides, not itself an admin-facing policy) returns [].
 */
interface ReplacementPolicyInterface
{
    public function id(): int;

    public function name(): string;

    public function validate(PolicyContext $context): PolicyValidationResult;

    public function evaluate(PolicyContext $context): PolicyEvaluationResult;

    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult;

    public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult;

    public function replacementLimitScope(): ReplacementLimitScope;

    public function extensionLimitScope(): ReplacementLimitScope;

    /**
     * @return array<string, mixed> Map of PolicySettingKey::value => default value
     */
    public static function overridableSettings(): array;
}