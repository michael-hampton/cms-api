<?php

namespace App\Services\OpenCollab\Policies;

use App\Models\Site;

interface ContributorPolicy
{
    /**
     * Draft creation is never blocked — safe action with no external side effects.
     */
    public function canCreateArticle(int $userId, Site $site): bool;

    /**
     * Publishing requires profile + contract + guidelines.
     * Payment is not a requirement for publishing.
     */
    public function canPublishArticle(int $userId, Site $site): bool;

    /**
     * Submitting for editorial review follows the same rules as publishing —
     * it triggers external side effects (notifications to admins, queue position).
     */
    public function canSubmitForReview(int $userId, Site $site): bool;

    /**
     * Withdrawing a payout requires full onboarding completion including payment.
     */
    public function canWithdraw(int $userId, Site $site): bool;

    /**
     * Earning money internally (ledger entries, revenue accrual) is never blocked.
     * A contributor can earn even if their payout details are incomplete.
     */
    public function canReceiveEarnings(int $userId, Site $site): bool;
}