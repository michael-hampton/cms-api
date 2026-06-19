<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionListingService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly NewsletterRepository $newsletterRepository,
        private readonly SubscriptionAccountStateResolver $stateResolver,
        private readonly SubscriptionContinuationResolver $continuationResolver,
        private readonly SubscriptionCancellationFlowProvider $cancellationFlowProvider,
        private readonly SubscriptionPaymentRecoveryService $paymentRecoveryService,
        private readonly SubscriptionPauseService $subscriptionPauseService,
    ) {
    }

    public function getGroupedSubscriptions(int $memberId, ?int $siteId = null): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->when($siteId !== null, function ($query) use ($siteId) {
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

            // Backward-compatible buckets. Remove only after every consumer has
            // migrated to current/action_required/previous.
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
            $group = $formatted['display_state']['group'];
            $grouped[$group][] = $formatted;

            $legacyGroup = $group === 'previous' ? 'expired' : 'active';
            $legacyType = $subscription->isPrint()
                ? SubscriptionType::PRINTED->value
                : SubscriptionType::DIGITAL->value;

            $grouped[$legacyGroup][$legacyType][] = $formatted;
        }

        return $grouped;
    }

    public function formatSubscriptionForListing(Subscription $subscription): array
    {
        $newsletters = $this->getAccessibleNewsletters($subscription);
        $archiveUrl = $this->getArchiveUrl($subscription);
        $displayState = $this->normaliseDisplayState(
            $subscription,
            $this->stateResolver->resolve($subscription)
        );
        $continuation = $this->continuationResolver->resolve($subscription, $displayState);
        $cancellationFlow = $this->cancellationFlowProvider->for($subscription);
        $paymentRecovery = $this->paymentRecoveryService->getListingData($subscription);
        $memberId = (int)$subscription->member_id;
        $actions = [];

        if ($this->subscriptionPauseService->canResumeSubscription($subscription, $memberId)) {
            $actions[] = [
                'key' => 'resume',
                'label' => 'Resume now',
                'type' => 'api',
                'method' => 'POST',
                'endpoint' => "/press-stack/account/subscriptions/{$subscription->id}/resume",
                'tone' => 'commercial',
            ];
        } elseif ($this->subscriptionPauseService->canPauseSubscription($subscription, $memberId)) {
            $actions[] = [
                'key' => 'pause',
                'label' => 'Pause',
                'type' => 'api',
                'method' => 'POST',
                'endpoint' => "/press-stack/account/subscriptions/{$subscription->id}/pause",
                'tone' => 'secondary',
            ];
        }

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
                'url' => "/press-stack/account/subscriptions/{$subscription->id}/settle-payment",
                'tone' => 'commercial',
            ];
        }

        $isCurrent = $displayState['group'] === 'current';
        $continuationKey = $continuation['key'] ?? null;
        $canRenew = in_array($continuationKey, ['renew', 'resubscribe'], true);

        return [
            'id' => $subscription->id,
            'site_id' => $subscription->site_id,
            'plan_name' => $subscription->plan_name,
            'type' => $subscription->isPrint()
                ? SubscriptionType::PRINTED->value
                : SubscriptionType::DIGITAL->value,
            'status' => $subscription->status,

            // New account contract.
            'is_current' => $isCurrent,

            // Backward-compatible contract.
            'is_active' => $isCurrent,
            'can_renew' => $canRenew,
            'should_show_renew' => $continuationKey === 'renew',

            'start_date' => $this->formatDate($subscription->start_date),
            'end_date' => $this->formatDate($subscription->end_date),
            'next_billing_date' => $this->formatDate($subscription->next_billing_date),
            'auto_renew' => (bool)$subscription->auto_renew,
            'newsletters' => $newsletters,
            'archive_url' => $archiveUrl,
            'premium_access' => $this->getPremiumAccessList($subscription),
            'plan_id' => $subscription->plan_id,
            'display_state' => $displayState,
            'facts' => $this->facts($subscription, $displayState),
            'benefits' => $this->benefits($newsletters, $archiveUrl),
            'actions' => $actions,
            'cancellation_flow' => $cancellationFlow,
            'payment_recovery' => $paymentRecovery,
        ];
    }

    public function getSubscriptionSummary(int $memberId, ?int $siteId = null): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->when($siteId !== null, function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->get();

        $states = $subscriptions->map(function (Subscription $subscription): array {
            return $this->normaliseDisplayState(
                $subscription,
                $this->stateResolver->resolve($subscription)
            );
        });

        $current = $states->filter(
            static fn(array $state): bool => $state['group'] === 'current'
        )->count();

        return [
            'total' => $subscriptions->count(),

            // New contract.
            'current' => $current,

            // Backward-compatible alias.
            'active' => $current,

            'action_required' => $states->filter(
                static fn(array $state): bool => $state['group'] === 'action_required'
            )->count(),
            'previous' => $states->filter(
                static fn(array $state): bool => $state['group'] === 'previous'
            )->count(),
            'expired' => $states->filter(
                static fn(array $state): bool => $state['key'] === 'expired'
            )->count(),
            'cancelled' => $states->filter(
                static fn(array $state): bool => $state['key'] === 'cancelled'
            )->count(),
        ];
    }

    private function normaliseDisplayState(Subscription $subscription, array $state): array
    {
        if (!$subscription->isCancellationScheduled()) {
            return $state;
        }

        $endDate = $this->formatDate($subscription->end_date);

        return [
            'key' => 'cancellation_scheduled',
            'group' => 'current',
            'label' => 'Cancellation scheduled',
            'tone' => 'warning',
            'accent' => 'amber',
            'copy' => $endDate
                ? "Access continues until {$endDate}."
                : 'Access continues until the current term ends.',
            'date_label' => 'Access until',
            'date_value' => $endDate,
        ];
    }

    private function facts(Subscription $subscription, array $displayState): array
    {
        $facts = [];

        if (!empty($displayState['date_label']) && !empty($displayState['date_value'])) {
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

    private function getAccessibleNewsletters(Subscription $subscription): array
    {
        $newsletters = [];
        $premiumAccess = $subscription->premiumAccess(true)
            ->where('premium_type', 'newsletter')
            ->where('is_active', true)
            ->get();

        foreach ($premiumAccess as $access) {
            $newsletter = Newsletter::where('site_id', $subscription->site_id)
                ->where('active', true)
                ->where(function ($query) use ($access) {
                    $query->where('slug', $access->premium_identifier)
                        ->orWhere('id', $access->premium_identifier);
                })
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

    private function getArchiveUrl(Subscription $subscription): ?string
    {
        return $subscription->isDigital() || $subscription->includes_digital_access
            ? '/newsletters/archive'
            : null;
    }

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
}
