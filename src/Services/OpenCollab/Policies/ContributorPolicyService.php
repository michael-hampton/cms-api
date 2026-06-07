<?php

namespace App\Services\OpenCollab\Policies;

use App\Models\Site;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\ContributorOnboardingService;

/**
 * Centralised permission decisions for contributor actions.
 *
 * Deliberately avoids isComplete() for publish/submit — those only need
 * the compliance-critical steps (profile, contract, guidelines).
 * Payment details are only required for financial withdrawal.
 *
 * This lets contributors who haven't set up payment yet still publish,
 * while ensuring they cannot withdraw until fully compliant.
 */
class ContributorPolicyService implements ContributorPolicy
{
    /**
     * Steps that block publishing and submission.
     * Payment/KYC are intentionally excluded — a contributor can publish without payout details.
     */
    private const PUBLISH_BLOCKING_STEPS = ['profile', 'contract', 'guidelines'];

    /**
     * All financial/onboarding steps block withdrawal — including payment setup and KYC.
     */
    private const WITHDRAW_BLOCKING_STEPS = ['profile', 'payment_setup', 'kyc_verification', 'contract', 'guidelines', 'age_verification'];

    public function __construct(
        private readonly ContributorOnboardingService $onboarding,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
    }

    public function canCreateArticle(int $userId, Site $site): bool
    {
        return $this->authorization->allowsAny($userId, $site->id, [
            'content.create',
            'content.edit_own',
        ]);
    }

    public function canPublishArticle(int $userId, Site $site): bool
    {
        return $this->authorization->allowsAny($userId, $site->id, [
            'content.publish',
            'content.submit',
        ]) && $this->hasNoPendingBlockingSteps($userId, $site, self::PUBLISH_BLOCKING_STEPS);
    }

    private function hasNoPendingBlockingSteps(int $userId, Site $site, array $blockingSteps): bool
    {
        $pending = $this->onboarding->pendingSteps($userId, $site);

        $blocking = array_filter(
            $pending,
            fn(array $step) => in_array($step['step'], $blockingSteps, true),
        );

        return empty($blocking);
    }

    public function canSubmitForReview(int $userId, Site $site): bool
    {
        return $this->authorization->allows($userId, $site->id, 'content.submit')
            && $this->hasNoPendingBlockingSteps($userId, $site, self::PUBLISH_BLOCKING_STEPS);
    }

    public function canWithdraw(int $userId, Site $site): bool
    {
        return $this->authorization->allows($userId, $site->id, 'payout.request')
            && $this->hasNoPendingBlockingSteps($userId, $site, self::WITHDRAW_BLOCKING_STEPS);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    public function canReceiveEarnings(int $userId, Site $site): bool
    {
        return true;
    }
}
