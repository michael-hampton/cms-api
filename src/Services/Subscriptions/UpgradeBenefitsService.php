<?php

namespace App\Services\Subscriptions;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;

class UpgradeBenefitsService
{
    public function __construct(
        private readonly array $benefitMap = []
    )
    {
    }

    public function getUpgradeBenefits(Subscription $subscription, SubscriptionPlan $upgradePlan): array
    {
        $benefits = [];

        $currentAccess = $subscription->premiumAccess();
        $newAccess = $upgradePlan->getPremiumAccessGrants();

        $currentAccessKeys = $currentAccess->map(
            fn($a) => $a->premium_type . ':' . $a->premium_identifier
        )->toArray();

        foreach ($newAccess as $access) {
            $key = $access['type'] . ':' . $access['identifier'];

            if (!in_array($key, $currentAccessKeys)) {
                $benefits[] = $this->getBenefitForAccess($access['type'], $access['identifier']);
            }
        }

        // Compare features
        $currentFeatures = $subscription->plan ? $subscription->plan->features : [];
        $upgradeFeatures = $upgradePlan->features;

        $newFeatures = array_diff($upgradeFeatures ?? [], $currentFeatures ?? []);

        foreach (array_slice($newFeatures, 0, 3) as $feature) {
            $benefits[] = [
                'icon' => '✨',
                'title' => 'New Feature',
                'description' => $feature
            ];
        }

        return $benefits;
    }

    private function getBenefitForAccess(string $type, string $identifier): array
    {
        $key = $type . ':' . $identifier;

        $benefitMap = !empty($this->benefitMap) ? $this->benefitMap : $this->getDefaultBenefitMap();

        return $benefitMap[$key] ?? [
            'icon' => '⭐',
            'title' => ucfirst($identifier),
            'description' => 'Premium ' . $type . ' access'
        ];
    }

    private function getDefaultBenefitMap(): array
    {
        return [
            'newsletter:insider' => [
                'icon' => '🔓',
                'title' => 'Unlock Insider Newsletter',
                'description' => 'Immediate access to all premium Insider articles and features'
            ],
            'newsletter:tech-weekly' => [
                'icon' => '💻',
                'title' => 'Tech Weekly Newsletter',
                'description' => 'Weekly technology insights and analysis'
            ],
            'newsletter:business-brief' => [
                'icon' => '📊',
                'title' => 'Business Brief Newsletter',
                'description' => 'Daily business news and market updates'
            ],
            'newsletter:politics-daily' => [
                'icon' => '🏛️',
                'title' => 'Politics Daily Newsletter',
                'description' => 'In-depth political coverage and analysis'
            ],
            'newsletter:sports-insider' => [
                'icon' => '⚽',
                'title' => 'Sports Insider Newsletter',
                'description' => 'Exclusive sports news and behind-the-scenes content'
            ],
            'archive:full' => [
                'icon' => '📚',
                'title' => 'Full Archive Access',
                'description' => 'Access our complete digital archive of past issues'
            ],
            'archive:recent' => [
                'icon' => '📰',
                'title' => 'Recent Archive Access',
                'description' => 'Access to articles from the past 12 months'
            ],
            'video:premium' => [
                'icon' => '🎥',
                'title' => 'Premium Video Content',
                'description' => 'Exclusive video interviews and documentaries'
            ],
            'video:live-events' => [
                'icon' => '📺',
                'title' => 'Live Event Streaming',
                'description' => 'Watch live coverage of exclusive events'
            ],
            'podcast:premium' => [
                'icon' => '🎙️',
                'title' => 'Premium Podcasts',
                'description' => 'Ad-free listening and exclusive bonus episodes'
            ],
            'community:forum' => [
                'icon' => '💬',
                'title' => 'Community Forum Access',
                'description' => 'Join discussions with other subscribers'
            ],
            'events:in-person' => [
                'icon' => '🎫',
                'title' => 'In-Person Events',
                'description' => 'Invitations to exclusive subscriber events'
            ],
            'events:virtual' => [
                'icon' => '🖥️',
                'title' => 'Virtual Events',
                'description' => 'Access to virtual Q&A sessions and webinars'
            ],
        ];
    }
}