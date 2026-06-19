<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionListingService
{
    private SubscriptionAccountStateResolver $stateResolver;
    private SubscriptionContinuationResolver $continuationResolver;
    private SubscriptionCancellationFlowProvider $cancellationFlowProvider;
    private SubscriptionPaymentRecoveryService $paymentRecoveryService;

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly NewsletterRepository   $newsletterRepository,
        ?SubscriptionAccountStateResolver $stateResolver = null,
        ?SubscriptionContinuationResolver $continuationResolver = null,
        ?SubscriptionCancellationFlowProvider $cancellationFlowProvider = null,
        ?SubscriptionPaymentRecoveryService $paymentRecoveryService = null,
    )
    {
        $this->stateResolver = $stateResolver ?? new SubscriptionAccountStateResolver();
        $this->continuationResolver = $continuationResolver ?? new SubscriptionContinuationResolver();
        $this->cancellationFlowProvider = $cancellationFlowProvider ?? new SubscriptionCancellationFlowProvider();
        $this->paymentRecoveryService = $paymentRecoveryService ?? new SubscriptionPaymentRecoveryService();
    }

    /**
     * Get grouped subscriptions for member
     * Groups by type (print, digital) and status (active, expired)
     */
    public function getGroupedSubscriptions(int $memberId, ?int $siteId = null): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->when(!empty($siteId), function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->with(['plan', 'premiumAccess'])
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [
            'current' => [],
            'action_required' => [],
            'previous' => [],
            'active' => [
                SubscriptionType::PRINTED->value => [],
                SubscriptionType::DIGITAL->value => [],
            ],
            'expired' => [
                SubscriptionType::PRINTED->value => [],
                SubscriptionType::DIGITAL->value => [],
            ],
        ];

        foreach ($subscriptions as $subscription) {
            $formatted = $this->formatSubscriptionForListing($subscription);
            $grouped[$formatted['display_state']['group']][] = $formatted;

            // Backward-compatible shape for callers not yet migrated.
            $status = $formatted['display_state']['group'] === 'previous' ? 'expired' : 'active';
            $type = $subscription->isPrint() ? SubscriptionType::PRINTED->value : SubscriptionType::DIGITAL->value;
            $grouped[$status][$type][] = $formatted;
        }

        return $grouped;
    }

    /**
     * Format subscription data for listing display
     */
    public function formatSubscriptionForListing(Subscription $subscription): array
    {
        $newsletters = $this->getAccessibleNewsletters($subscription);
        $displayState = $this->stateResolver->resolve($subscription);
        $continuation = $this->continuationResolver->resolve($subscription, $displayState);
        $cancellationFlow = $this->cancellationFlowProvider->for($subscription);
        $paymentRecovery = $this->paymentRecoveryService->getRecoveryData($subscription);
        $actions = [];

        if ($cancellationFlow) {
            $actions[] = [
                'key' => 'cancel',
                'label' => $cancellationFlow['action_label'],
                'type' => 'modal',
                'modal' => 'cancel',
                'tone' => 'secondary',
            ];
        }

        if ($continuation) {
            $actions[] = $continuation;
        }

        if ($paymentRecovery) {
            $actions[] = [
                'key' => 'settle_payment',
                'label' => 'Settle ' . $paymentRecovery['amount'],
                'type' => 'redirect',
                'url' => '/api/' . \App\Framework\Support\SiteContext::slug()
                    . "/member/account/subscriptions/{$subscription->id}/settle-payment",
                'tone' => 'commercial',
            ];
        }

        return [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan_name,
            'type' => $subscription->isPrint() ? SubscriptionType::PRINTED->value : SubscriptionType::DIGITAL->value,
            'status' => $subscription->status,
            'is_active' => $displayState['group'] === 'current',
            'start_date' => $this->formatDate($subscription->start_date),
            'end_date' => $this->formatDate($subscription->end_date),
            'next_billing_date' => $this->formatDate($subscription->next_billing_date),
            'auto_renew' => $subscription->auto_renew,
            'can_renew' => $this->canRenew($subscription),
            'should_show_renew' => $this->shouldShowRenewCTA($subscription),
            'newsletters' => $newsletters,
            'archive_url' => $this->getArchiveUrl($subscription),
            'premium_access' => $this->getPremiumAccessList($subscription),
            'plan_id' => $subscription->plan_id,
            'display_state' => $displayState,
            'facts' => $this->facts($subscription, $displayState),
            'benefits' => $this->benefits($newsletters, $this->getArchiveUrl($subscription)),
            'actions' => $actions,
            'cancellation_flow' => $cancellationFlow,
            'payment_recovery' => $paymentRecovery,
        ];
    }

    private function facts(Subscription $subscription, array $displayState): array
    {
        $facts = [];

        if ($displayState['date_label'] && $displayState['date_value']) {
            $facts[] = [
                'label' => $displayState['date_label'],
                'value' => $displayState['date_value'],
            ];
        }

        if ($subscription->start_date) {
            $facts[] = [
                'label' => 'Started',
                'value' => $this->formatDate($subscription->start_date),
            ];
        }

        if ($subscription->auto_renew && $subscription->next_billing_date) {
            $facts[] = [
                'label' => 'Next billing',
                'value' => $this->formatDate($subscription->next_billing_date),
            ];
        }

        return $facts;
    }

    private function benefits(array $newsletters, ?string $archiveUrl): array
    {
        $benefits = [];

        if ($archiveUrl) {
            $benefits[] = ['label' => 'Archive access', 'url' => $archiveUrl];
        }

        foreach ($newsletters as $newsletter) {
            $benefits[] = ['label' => $newsletter['title'], 'url' => null];
        }

        return $benefits;
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('j M Y');
        }

        if (is_string($date) && $date !== '') {
            $timestamp = strtotime($date);
            return $timestamp === false ? null : date('j M Y', $timestamp);
        }

        return null;
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

        $states = $subscriptions->map(fn($subscription) => $this->stateResolver->resolve($subscription));
        $active = $states->filter(fn($state) => $state['group'] === 'current')->count();
        $actionRequired = $states->filter(fn($state) => $state['group'] === 'action_required')->count();
        $expired = $states->filter(fn($state) => $state['key'] === 'expired')->count();
        $cancelled = $states->filter(fn($state) => $state['key'] === 'cancelled')->count();

        return [
            'total' => $subscriptions->count(),
            'active' => $active,
            'action_required' => $actionRequired,
            'previous' => $states->filter(fn($state) => $state['group'] === 'previous')->count(),
            'expired' => $expired,
            'cancelled' => $cancelled,
        ];
    }
}
