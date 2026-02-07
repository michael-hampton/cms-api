<?php

namespace App\Services\Newsletter;

use App\Enums\Newsletters\NewsletterAccessResult;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class NewsletterAccessService
{
    public function __construct(
        private readonly NewsletterRepository   $newsletterRepository,
        private readonly SubscriptionRepository $subscriptionRepository
    )
    {
    }

    public function checkAccess(int $newsletterId, ?int $memberId, int $siteId): array
    {
        $newsletter = $this->newsletterRepository->find($newsletterId);

        if (!$newsletter || $newsletter->site_id !== $siteId) {
            return [
                'has_access' => false,
                'reason' => NewsletterAccessResult::NOT_FOUND->value,
                'message' => 'Newsletter not found'
            ];
        }

        if (!$newsletter->isPremium()) {
            return [
                'has_access' => true,
                'reason' => NewsletterAccessResult::FREE->value
            ];
        }

        // Paid newsletters require authentication
        if (!$memberId) {
            return [
                'has_access' => false,
                'reason' => NewsletterAccessResult::AUTHENTICATION_REQUIRED->value,
                'message' => 'Please log in to access this newsletter'
            ];
        }

        // Check active subscription
        $subscription = $this->subscriptionRepository
            ->getActiveSubscriptionForMember($memberId, $siteId);

        if (!$subscription) {
            return [
                'has_access' => false,
                'reason' => NewsletterAccessResult::NO_SUBSCRIPTION->value,
                'message' => 'Subscribe to access this newsletter'
            ];
        }

        $canAccessNewsletter = $subscription->canAccessNewsletter($newsletter);

        // Let the subscription decide if it can access this newsletter
        if (!$canAccessNewsletter->allowed) {
            return [
                'has_access' => false,
                'reason' => NewsletterAccessResult::INSUFFICIENT_LEVEL->value,
                'message' => 'Upgrade your subscription to access this newsletter',
                'required_level' => $newsletter->access_level
            ];
        }

        return [
            'has_access' => true,
            'reason' => NewsletterAccessResult::SUBSCRIPTION->value
        ];
    }
}