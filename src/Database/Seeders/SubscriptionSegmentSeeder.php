<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Segment;
use App\Models\SegmentRule;

class SubscriptionSegmentSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [

            [
                'key' => 'renewal_due_90_days',
                'name' => 'Renewal Due In 90 Days',
                'description' => 'Subscriptions renewing within the next 90 days.',
                'category' => 'renewal',
                'subject_type' => 'subscription',
                'priority' => 100,
            ],

            [
                'key' => 'renewal_due_30_days',
                'name' => 'Renewal Due In 30 Days',
                'description' => 'Subscriptions renewing within the next 30 days.',
                'category' => 'renewal',
                'subject_type' => 'subscription',
                'priority' => 90,
            ],

            [
                'key' => 'renewal_due_7_days',
                'name' => 'Renewal Due In 7 Days',
                'description' => 'Subscriptions renewing within the next 7 days.',
                'category' => 'renewal',
                'subject_type' => 'subscription',
                'priority' => 80,
            ],

            [
                'key' => 'renewal_due_today',
                'name' => 'Renewal Due Today',
                'description' => 'Subscriptions renewing today.',
                'category' => 'renewal',
                'subject_type' => 'subscription',
                'priority' => 70,
            ],

            [
                'key' => 'renewal_failed_payment',
                'name' => 'Renewal Payment Failed',
                'description' => 'Renewal attempt failed due to payment issues.',
                'category' => 'renewal',
                'subject_type' => 'subscription',
                'priority' => 10,
            ],

            [
                'key' => 'cancelled_before_renewal',
                'name' => 'Cancelled Before Renewal',
                'description' => 'Subscription cancelled before scheduled renewal.',
                'category' => 'retention',
                'subject_type' => 'subscription',
                'priority' => 20,
            ],

            [
                'key' => 'price_rise_under_10_percent_dd',
                'name' => 'Direct Debit Price Rise Under 10%',
                'description' => 'Direct debit subscribers impacted by a price increase below 10%.',
                'category' => 'pricing',
                'subject_type' => 'subscription',
                'priority' => 15,
            ],

            [
                'key' => 'price_rise_over_10_percent_dd',
                'name' => 'Direct Debit Price Rise Over 10%',
                'description' => 'Direct debit subscribers impacted by a price increase over 10%.',
                'category' => 'pricing',
                'subject_type' => 'subscription',
                'priority' => 5,
            ],

            [
                'key' => 'active_auto_renew',
                'name' => 'Active Auto Renew',
                'description' => 'Active subscriptions with auto-renew enabled.',
                'category' => 'lifecycle',
                'subject_type' => 'subscription',
                'priority' => 200,
            ],

            [
                'key' => 'active_manual_renewal',
                'name' => 'Active Manual Renewal',
                'description' => 'Active subscriptions requiring manual renewal.',
                'category' => 'lifecycle',
                'subject_type' => 'subscription',
                'priority' => 210,
            ],

            [
                'key' => 'expired_subscription',
                'name' => 'Expired Subscription',
                'description' => 'Subscription has expired.',
                'category' => 'lifecycle',
                'subject_type' => 'subscription',
                'priority' => 30,
            ],

            [
                'key' => 'winback_candidate',
                'name' => 'Win Back Candidate',
                'description' => 'Expired or cancelled subscriptions eligible for retention campaigns.',
                'category' => 'retention',
                'subject_type' => 'subscription',
                'priority' => 40,
            ],

            // ---------------------------------------------------------------
            // Initial, non-manually-created segments derived from the offer
            // (subscription_plan_pricing row) used at purchase. These are the
            // baseline classification for a subscription and are intentionally
            // low priority (see plan_segment.priority in
            // FreeAndPaidSubscriptionPlanSegmentSeeder) so more specific
            // rule-based segments above take precedence when they also match.
            // ---------------------------------------------------------------

            [
                'key' => 'free_subscription',
                'name' => 'Free Subscription',
                'description' => 'Subscription purchased via an offer at zero cost.',
                'category' => 'offer',
                'subject_type' => 'subscription',
                'priority' => 300,
            ],

            [
                'key' => 'paid_subscription',
                'name' => 'Paid Subscription',
                'description' => 'Subscription purchased via an offer at a non-zero price.',
                'category' => 'offer',
                'subject_type' => 'subscription',
                'priority' => 310,
            ],
        ];

        foreach ($segments as $segment) {

            Segment::updateOrCreate(
                [
                    'key' => $segment['key'],
                ],
                [
                    ...$segment,
                    'is_active' => true,
                ]
            );
        }

        $this->seedOfferDerivedRules();
    }

    /**
     * Rules for the free_subscription / paid_subscription segments. Fields
     * are bare model attribute paths (not the dot-prefixed UI registry
     * paths) — SegmentRuleEngine matches rules against
     * Subscription::toArray() directly.
     *
     * Replaces all rules on each run so changes here take effect on re-seed.
     */
    private function seedOfferDerivedRules(): void
    {
        $this->rules('free_subscription', [
            ['field' => 'is_offer', 'operator' => '=', 'value' => true, 'boolean' => 'AND'],
            ['field' => 'price', 'operator' => '=', 'value' => 0, 'boolean' => 'AND'],
        ]);

        $this->rules('paid_subscription', [
            ['field' => 'is_offer', 'operator' => '=', 'value' => true, 'boolean' => 'AND'],
            ['field' => 'price', 'operator' => '>', 'value' => 0, 'boolean' => 'AND'],
        ]);
    }

    private function rules(string $segmentKey, array $rules): void
    {
        $segment = Segment::where('key', $segmentKey)->first();

        if ($segment === null) {
            return;
        }

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
}