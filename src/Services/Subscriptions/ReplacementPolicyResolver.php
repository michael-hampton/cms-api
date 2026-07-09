<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Support\Logger;
use App\Models\ReplacementPolicy;
use App\Repositories\Subscriptions\ReplacementPolicyRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;
use App\Services\Subscriptions\Policies\GoodwillPolicy;
use RuntimeException;

/**
 * Resolves which ReplacementPolicyInterface strategy governs a
 * subscription, plan, or business override — instantiating the concrete
 * policy class named on the matched replacement_policies row.
 *
 * Priority for plan/subscription resolution:
 *   1. Plan's assigned policy (if active)
 *   2. Site's default policy (if active) — logged as a fallback
 *   3. Configuration exception if neither exists
 *
 * This class only resolves — it does not evaluate whether a given
 * replacement/extension is allowed under the resolved policy. That's the
 * resolved policy's own evaluate() method.
 */
class ReplacementPolicyResolver
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ReplacementPolicyRepository $policyRepository,
    ) {
    }

    public function resolveForSubscription(int $subscriptionId, int $siteId): ReplacementPolicyInterface
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException('Subscription not found.');
        }

        return $this->resolveForPlan((int) $subscription->plan_id, $siteId, $subscriptionId);
    }

    public function resolveForPlan(int $planId, int $siteId, ?int $subscriptionId = null): ReplacementPolicyInterface
    {
        $policyModel = $this->policyRepository->findForPlan($planId);

        if ($policyModel) {
            return $this->instantiate($policyModel);
        }

        return $this->resolveDefault($siteId, $subscriptionId, $planId);
    }

    public function resolveDefault(int $siteId, ?int $subscriptionId = null, ?int $planId = null): ReplacementPolicyInterface
    {
        $default = $this->policyRepository->findDefault($siteId);

        if (!$default) {
            throw new RuntimeException(
                "No default replacement policy is configured for site #{$siteId}."
            );
        }

        Logger::warning('Default replacement policy used', [
            'subscription_id' => $subscriptionId,
            'plan_id' => $planId,
            'policy_used' => $default->id,
            'reason' => $planId
                ? 'Plan has no active replacement policy assigned.'
                : 'No plan context available when resolving replacement policy.',
        ]);

        return $this->instantiate($default);
    }

    /**
     * Resolves the site's GoodwillPolicy row, used when an agent-authorised
     * business override substitutes for a denied plan policy. Requires a
     * seeded replacement_policies row per site with
     * policy_class = GoodwillPolicy::class (see ReplacementPolicySeeder).
     *
     * Requires a new ReplacementPolicyRepository::findByClass() method —
     * see the accompanying patch note, since the original repository file
     * wasn't available to edit directly here.
     */
    public function resolveGoodwill(int $siteId): ReplacementPolicyInterface
    {
        $goodwill = $this->policyRepository->findByClass(GoodwillPolicy::class, $siteId);

        if (!$goodwill) {
            throw new RuntimeException(
                "No goodwill override policy is configured for site #{$siteId}."
            );
        }

        return $this->instantiate($goodwill);
    }

    private function instantiate(ReplacementPolicy $policyModel): ReplacementPolicyInterface
    {
        $class = $policyModel->policy_class;

        if (!$class || !class_exists($class)) {
            throw new RuntimeException(
                "Replacement policy #{$policyModel->id} has an invalid or missing policy_class."
            );
        }

        $instance = new $class($policyModel);

        if (!$instance instanceof ReplacementPolicyInterface) {
            throw new RuntimeException(
                "Class {$class} does not implement ReplacementPolicyInterface."
            );
        }

        return $instance;
    }
}
