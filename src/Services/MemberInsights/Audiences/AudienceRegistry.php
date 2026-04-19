<?php

namespace App\Services\MemberInsights\Audiences;

/**
 * Code-based audience registry.
 *
 * Each entry is a human-readable label + a resolver closure that receives
 * a member's structured profile array (built by MemberStatEngine / BuildMemberProfileJob)
 * and returns bool.
 *
 * Design rules:
 *   - Resolvers MUST be pure: they read $profile only, perform no I/O.
 *   - Every resolver uses data_get() with a safe default so missing keys
 *     never cause exceptions.
 *   - No audience is defined "for future use" — each one is consumed by
 *     at least one newsletter block, campaign, or preview system.
 *   - Labels are written for admins, not developers.
 *
 * Adding a new audience:
 *   1. Add an entry to all() with a unique snake_case key.
 *   2. Reference the same key in newsletter_blocks.audience_key or
 *      CampaignConsentChecker purpose mapping.
 *   3. Add a unit test in AudienceRegistryTest.
 */
final class AudienceRegistry
{
    /**
     * Return just labels keyed by audience key — for admin dropdowns.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_map(fn(array $entry) => $entry['label'], $this->all());
    }

    /**
     * @return array<string, array{label: string, resolver: callable}>
     */
    public function all(): array
    {
        return [

            // ── Universal ──────────────────────────────────────────────────
            'all_users' => [
                'label' => 'All Users',
                'resolver' => fn(array $profile): bool => true,
            ],

            // ── Activity-based ─────────────────────────────────────────────
            'inactive_users' => [
                'label' => 'Inactive Users (7+ days)',
                'resolver' => fn(array $profile): bool => data_get($profile, 'behaviour.last_active_days', 0) > 7,
            ],

            'highly_active_users' => [
                'label' => 'Highly Active Users',
                'resolver' => fn(array $profile): bool => data_get($profile, 'scores.activity_score', 0) > 80,
            ],

            'new_members' => [
                'label' => 'New Members (joined ≤ 30 days)',
                'resolver' => fn(array $profile): bool => data_get($profile, 'behaviour.is_new_user') === true,
            ],

            'at_risk_churn' => [
                'label' => 'At Risk of Churning',
                'resolver' => fn(array $profile): bool => data_get($profile, 'behaviour.churn_risk') === 'high',
            ],

            'medium_churn_risk' => [
                'label' => 'Medium Churn Risk',
                'resolver' => fn(array $profile): bool => data_get($profile, 'behaviour.churn_risk') === 'medium',
            ],

            // ── Engagement depth ───────────────────────────────────────────
            'high_engagement' => [
                'label' => 'High Engagement Score',
                'resolver' => fn(array $profile): bool => data_get($profile, 'scores.engagement_score', 0) > 70,
            ],

            'low_engagement' => [
                'label' => 'Low Engagement Score',
                'resolver' => fn(array $profile): bool => data_get($profile, 'scores.engagement_score', 0) < 20,
            ],

            // ── Activity frequency ─────────────────────────────────────────
            'high_frequency' => [
                'label' => 'High Activity Frequency',
                'resolver' => fn(array $profile): bool => data_get($profile, 'behaviour.activity_frequency') === 'high',
            ],

            'low_frequency' => [
                'label' => 'Low Activity Frequency',
                'resolver' => fn(array $profile): bool => data_get($profile, 'behaviour.activity_frequency') === 'low',
            ],

            // ── Content behaviour ──────────────────────────────────────────
            'content_readers' => [
                'label' => 'Active Content Readers',
                'resolver' => fn(array $profile): bool => data_get($profile, 'stats.total_pages_read', 0) > 10,
            ],

            'commenters' => [
                'label' => 'Active Commenters',
                'resolver' => fn(array $profile): bool => data_get($profile, 'stats.total_comments', 0) > 5,
            ],

            'likers' => [
                'label' => 'Active Likers',
                'resolver' => fn(array $profile): bool => data_get($profile, 'stats.total_likes', 0) > 10,
            ],

            // ── Trend-based ────────────────────────────────────────────────
            'trending_up' => [
                'label' => 'Activity Trending Up',
                'resolver' => fn(array $profile): bool => data_get($profile, 'trends.7d_change', 0) > 20,
            ],

            'trending_down' => [
                'label' => 'Activity Trending Down',
                'resolver' => fn(array $profile): bool => data_get($profile, 'trends.7d_change', 0) < -20,
            ],
        ];
    }
}