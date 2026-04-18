<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Campaign;
use App\Models\Segment;
use App\Models\SegmentRule;
use App\Models\Site;

/**
 * Seeds the minimum viable set of segments, rules, and campaigns.
 *
 * Idempotent: uses firstOrCreate / updateOrCreate so it is safe to
 * re-run without duplicating rows.
 *
 * Segment categories:
 *   activation    → new / onboarding
 *   engagement    → active positive behaviour
 *   retention     → churn / drop-off
 *   behaviour     → how the member uses the platform
 *   monetisation  → purchasing / spending signals
 */
class SegmentAndCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSegments();
        $this->seedCampaigns();
    }

    // =========================================================================
    // Segments
    // =========================================================================

    private function seedSegments(): void
    {
        // -----------------------------------------------------------------
        // Activation
        // -----------------------------------------------------------------

        $newUser = $this->segment(
            key: 'new_user',
            name: 'New User',
            description: 'Member has fewer than 5 total actions — still in onboarding window.',
            category: 'activation',
        );
        $this->rules($newUser, [
            ['field' => 'summary.total_actions', 'operator' => '<', 'value' => 5, 'boolean' => 'AND'],
        ]);

        $inactiveNewUser = $this->segment(
            key: 'inactive_new_user',
            name: 'Inactive New User',
            description: 'Joined but has not performed any actions and has zero active days.',
            category: 'activation',
        );
        $this->rules($inactiveNewUser, [
            ['field' => 'summary.total_actions', 'operator' => '<', 'value' => 3, 'boolean' => 'AND'],
            ['field' => 'summary.active_days', 'operator' => '=', 'value' => 0, 'boolean' => 'AND'],
        ]);

        // -----------------------------------------------------------------
        // Engagement
        // -----------------------------------------------------------------

        $highlyActive = $this->segment(
            key: 'highly_active',
            name: 'Highly Active',
            description: 'Activity score above 80 — deeply engaged member.',
            category: 'engagement',
        );
        $this->rules($highlyActive, [
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80, 'boolean' => 'AND'],
        ]);

        $engagedContributor = $this->segment(
            key: 'engaged_contributor',
            name: 'Engaged Contributor',
            description: 'Behaviour profile is classified as an engaged contributor (comment-heavy).',
            category: 'engagement',
        );
        $this->rules($engagedContributor, [
            ['field' => 'behaviour.profile_type', 'operator' => '=', 'value' => 'engaged_contributor', 'boolean' => 'AND'],
        ]);

        $powerUser = $this->segment(
            key: 'power_user',
            name: 'Power User',
            description: 'More than 500 total actions — super-engaged member.',
            category: 'engagement',
        );
        $this->rules($powerUser, [
            ['field' => 'summary.total_actions', 'operator' => '>', 'value' => 500, 'boolean' => 'AND'],
        ]);

        // -----------------------------------------------------------------
        // Retention / Churn
        // -----------------------------------------------------------------

        $churning = $this->segment(
            key: 'churning',
            name: 'Churning',
            description: '7-day activity trend has dropped more than 20% — risk of churn.',
            category: 'retention',
        );
        $this->rules($churning, [
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -20, 'boolean' => 'AND'],
        ]);

        $decliningUser = $this->segment(
            key: 'declining_user',
            name: 'Declining User',
            description: '7-day trend down more than 10% — early churn signal.',
            category: 'retention',
        );
        $this->rules($decliningUser, [
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -10, 'boolean' => 'AND'],
        ]);

        $lurker = $this->segment(
            key: 'lurker',
            name: 'Lurker',
            description: 'Over 80% of activity is page views — passive consumer profile.',
            category: 'retention',
        );
        $this->rules($lurker, [
            ['field' => 'flags', 'operator' => 'CONTAINS', 'value' => 'lurker_profile', 'boolean' => 'AND'],
        ]);

        // -----------------------------------------------------------------
        // Behaviour
        // -----------------------------------------------------------------

        $browsingHeavy = $this->segment(
            key: 'browsing_heavy',
            name: 'Browsing Heavy',
            description: 'Dominant activity type is page views.',
            category: 'behaviour',
        );
        $this->rules($browsingHeavy, [
            ['field' => 'behaviour.profile_type', 'operator' => '=', 'value' => 'browsing_heavy', 'boolean' => 'AND'],
        ]);

        $reactiveUser = $this->segment(
            key: 'reactive_user',
            name: 'Reactive User',
            description: 'Dominant activity type is liking content.',
            category: 'behaviour',
        );
        $this->rules($reactiveUser, [
            ['field' => 'behaviour.profile_type', 'operator' => '=', 'value' => 'reactive_user', 'boolean' => 'AND'],
        ]);

        // -----------------------------------------------------------------
        // Monetisation
        // -----------------------------------------------------------------

        $highValueUser = $this->segment(
            key: 'high_value_user',
            name: 'High Value User',
            description: 'Engagement score above 90 — highest-tier engaged member.',
            category: 'monetisation',
        );
        $this->rules($highValueUser, [
            ['field' => 'scores.engagement_score', 'operator' => '>', 'value' => 90, 'boolean' => 'AND'],
        ]);

        $potentialBuyer = $this->segment(
            key: 'potential_buyer',
            name: 'Potential Buyer',
            description: 'High product interest combined with moderate activity score.',
            category: 'monetisation',
        );
        $this->rules($potentialBuyer, [
            ['field' => 'counters.order_count', 'operator' => '=', 'value' => 0, 'boolean' => 'AND'],
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 40, 'boolean' => 'AND'],
        ]);
    }

    // =========================================================================
    // Campaigns
    // =========================================================================

    private function segment(string $key, string $name, string $description, string $category): Segment
    {
        return Segment::updateOrCreate(
            ['key' => $key],
            [
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'is_active' => true,
            ]
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Replaces all rules for the segment on each seed run so that
     * rule changes in this file take effect when the seeder is re-run.
     */
    private function rules(Segment $segment, array $rules): void
    {
        SegmentRule::where('segment_id', $segment->id)->delete();

        foreach ($rules as $order => $rule) {
            SegmentRule::create([
                'segment_id' => $segment->id,
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => json_encode($rule['value'], JSON_THROW_ON_ERROR),
                'boolean' => $rule['boolean'] ?? 'AND',
                'sort_order' => $order,
            ]);
        }
    }

    private function seedCampaigns(): void
    {
        // Activation
        $this->campaign('welcome_series', 'new_user', 'email', 'App\Notifications\WelcomeSeriesNotification', cooldown: 0, priority: 80);
        $this->campaign('complete_your_profile', 'inactive_new_user', 'email', 'App\Notifications\CompleteProfileNotification', cooldown: 72, priority: 75);

        // Engagement
        $this->campaign('reward_engagement', 'highly_active', 'notification', 'App\Notifications\RewardEngagementNotification', cooldown: 168, priority: 50);
        $this->campaign('create_more_content', 'engaged_contributor', 'email', 'App\Notifications\CreateMoreContentNotification', cooldown: 168, priority: 45);
        $this->campaign('early_access', 'power_user', 'notification', 'App\Notifications\EarlyAccessNotification', cooldown: 336, priority: 40);

        // Retention
        $this->campaign('we_miss_you', 'churning', 'email', 'App\Notifications\WeMissYouNotification', cooldown: 48, priority: 100);
        $this->campaign('soft_reengagement', 'declining_user', 'email', 'App\Notifications\SoftReengagementNotification', cooldown: 48, priority: 90);
        $this->campaign('start_engaging', 'lurker', 'push', 'App\Notifications\StartEngagingNotification', cooldown: 72, priority: 60);

        // Behaviour
        $this->campaign('convert_to_action', 'browsing_heavy', 'notification', 'App\Notifications\ConvertToActionNotification', cooldown: 48, priority: 30);
        $this->campaign('nudge_to_comment', 'reactive_user', 'push', 'App\Notifications\NudgeToCommentNotification', cooldown: 48, priority: 25);

        // Monetisation
        $this->campaign('vip_rewards', 'high_value_user', 'email', 'App\Notifications\VipRewardsNotification', cooldown: 168, priority: 55);
        $this->campaign('recommended_products', 'potential_buyer', 'email', 'App\Notifications\RecommendedProductsNotification', cooldown: 72, priority: 35);
    }

    private function campaign(
        string $key,
        string $segmentKey,
        string $channel,
        string $template,
        int    $cooldown,
        int    $priority,
    ): void
    {
        $segment = Segment::where('key', $segmentKey)->first();

        if ($segment === null) {
            throw new \RuntimeException("Segment [{$segmentKey}] must exist before seeding campaign [{$key}].");
        }

        $site = Site::where('is_default', true)->first() ?? Site::first();

        if ($site === null) {
            throw new \RuntimeException("A site must exist before seeding campaign [{$key}].");
        }

        Campaign::updateOrCreate(
            ['slug' => $key],
            [
                'site_id' => $site->id,
                'name' => ucwords(str_replace('_', ' ', $key)),
                'description' => "Auto-seeded segmentation campaign for [{$segmentKey}].",
                'status' => 'active',
                'campaign_type' => 'segmentation',
                'segment_id' => $segment->id,
                'channel' => $channel,
                'template' => $template,
                'cooldown_hours' => $cooldown,
                'priority' => $priority,
                'is_active' => true,
            ]
        );
    }
}
