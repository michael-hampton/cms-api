<?php

namespace App\Services\Subscriptions;

use App\Models\Newsletter;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionListingService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly NewsletterRepository   $newsletterRepository
    )
    {
    }

    /**
     * Get grouped subscriptions for member
     * Groups by type (print, digital) and status (active, expired)
     */
    public function getGroupedSubscriptions(int $memberId, int $siteId): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->with(['plan', 'premiumAccess'])
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [
            'active' => [
                'print' => [],
                'digital' => [],
            ],
            'expired' => [
                'print' => [],
                'digital' => [],
            ],
        ];

        foreach ($subscriptions as $subscription) {
            $status = $subscription->isActive() ? 'active' : 'expired';
            $type = $subscription->isPrint() ? 'print' : 'digital';

            $grouped[$status][$type][] = $this->formatSubscriptionForListing($subscription);
        }

        return $grouped;
    }

    /**
     * Format subscription data for listing display
     */
    private function formatSubscriptionForListing(Subscription $subscription): array
    {
        $newsletters = $this->getAccessibleNewsletters($subscription);

        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan_name,
            'type' => $subscription->isPrint() ? 'print' : 'digital',
            'status' => $subscription->status,
            'is_active' => $subscription->isActive(),
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'next_billing_date' => $subscription->next_billing_date,
            'auto_renew' => $subscription->auto_renew,
            'can_renew' => $this->canRenew($subscription),
            'should_show_renew' => $this->shouldShowRenewCTA($subscription),
            'newsletters' => $newsletters,
            'archive_url' => $this->getArchiveUrl($subscription),
            'premium_access' => $this->getPremiumAccessList($subscription),
        ];
    }

    /**
     * Get newsletters accessible with this subscription
     */
    private function getAccessibleNewsletters(Subscription $subscription): array
    {
        $newsletters = [];

        // Get premium newsletter access
        $premiumAccess = $subscription->premiumAccess(true)
            ->where('premium_type', 'newsletter')
            ->where('is_active', true)
            ->get();

        foreach ($premiumAccess as $access) {
            $newsletter = Newsletter::where('site_id', $subscription->site_id)
                ->where('active', true)
                ->where('slug', $access->premium_identifier)
                ->orWhere('id', $access->premium_identifier)
                ->first();

            if ($newsletter) {
                $newsletters[] = [
                    'id' => $newsletter->id,
                    'title' => $newsletter->title,
                    'identifier' => $access->premium_identifier,
                ];
            }
        }

        return $newsletters;
    }

    /**
     * Check if subscription can be renewed
     */
    private function canRenew(Subscription $subscription): bool
    {
        // Can renew if expired or about to expire
        if ($subscription->status === 'expired') {
            return true;
        }

        if ($subscription->status === 'cancelled') {
            return true;
        }

        // Can renew if within 30 days of expiration
        if ($subscription->end_date) {
            $daysUntilExpiry = $subscription->end_date->diff(new \DateTime())->days;
            return $daysUntilExpiry <= 30;
        }

        return false;
    }

    /**
     * Check if should show renew CTA
     */
    private function shouldShowRenewCTA(Subscription $subscription): bool
    {
        if (!$this->canRenew($subscription)) {
            return false;
        }

        // Don't show if auto-renew is enabled and subscription is active
        if ($subscription->auto_renew && $subscription->isActive()) {
            return false;
        }

        return true;
    }

    /**
     * Get archive URL for subscription
     */
    private function getArchiveUrl(Subscription $subscription): ?string
    {
        if ($subscription->isDigital() || $subscription->includes_digital_access) {
            return "/newsletters/archive"; // Or specific archive based on subscription
        }

        return null;
    }

    /**
     * Get premium access list
     */
    private function getPremiumAccessList(Subscription $subscription): array
    {
        $access = [];

        $premiumAccess = $subscription->premiumAccess(true)
            ->where('is_active', true)
            ->get();

        foreach ($premiumAccess as $item) {
            $access[] = [
                'type' => $item->premium_type,
                'identifier' => $item->premium_identifier,
                'granted_at' => $item->granted_at,
                'expires_at' => $item->expires_at,
            ];
        }

        return $access;
    }

    /**
     * Get subscription summary statistics
     */
    public function getSubscriptionSummary(int $memberId, int $siteId): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->get();

        $active = $subscriptions->filter(fn($s) => $s->isActive())->count();
        $expired = $subscriptions->filter(fn($s) => $s->status === 'expired')->count();
        $cancelled = $subscriptions->filter(fn($s) => $s->status === 'cancelled')->count();

        return [
            'total' => $subscriptions->count(),
            'active' => $active,
            'expired' => $expired,
            'cancelled' => $cancelled,
        ];
    }
}