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
    ) {
    }

    public function getGroupedSubscriptions(int $memberId, ?int $siteId = null): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->when($siteId !== null, function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->with(['plan', 'premiumAccess'])
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = ['current' => [], 'action_required' => [], 'previous' => []];

        foreach ($subscriptions as $subscription) {
            $formatted = $this->formatSubscriptionForListing($subscription);
            $grouped[$formatted['display_state']['group']][] = $formatted;
        }

        return $grouped;
    }

    public function formatSubscriptionForListing(Subscription $subscription): array
    {
        $newsletters = $this->getAccessibleNewsletters($subscription);
        $archiveUrl = $this->getArchiveUrl($subscription);
        $displayState = $this->stateResolver->resolve($subscription);
        $continuation = $this->continuationResolver->resolve($subscription, $displayState);
        $cancellationFlow = $this->cancellationFlowProvider->for($subscription);
        $paymentRecovery = $this->paymentRecoveryService->getListingData($subscription);
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
                'url' => "/press-stack/account/subscriptions/{$subscription->id}/settle-payment",
                'tone' => 'commercial',
            ];
        }

        return [
            'id' => $subscription->id,
            'site_id' => $subscription->site_id,
            'plan_name' => $subscription->plan_name,
            'type' => $subscription->isPrint() ? SubscriptionType::PRINTED->value : SubscriptionType::DIGITAL->value,
            'status' => $subscription->status,
            'is_current' => $displayState['group'] === 'current',
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

        $states = $subscriptions->map(fn($subscription) => $this->stateResolver->resolve($subscription));

        return [
            'total' => $subscriptions->count(),
            'current' => $states->filter(fn($state) => $state['group'] === 'current')->count(),
            'action_required' => $states->filter(fn($state) => $state['group'] === 'action_required')->count(),
            'previous' => $states->filter(fn($state) => $state['group'] === 'previous')->count(),
            'expired' => $states->filter(fn($state) => $state['key'] === 'expired')->count(),
            'cancelled' => $states->filter(fn($state) => $state['key'] === 'cancelled')->count(),
        ];
    }

    private function facts(Subscription $subscription, array $displayState): array
    {
        $facts = [];

        if ($displayState['date_label'] && $displayState['date_value']) {
            $facts[] = ['label' => $displayState['date_label'], 'value' => $displayState['date_value']];
        }

        if ($subscription->start_date) {
            $facts[] = ['label' => 'Started', 'value' => $this->formatDate($subscription->start_date)];
        }

        if ($subscription->auto_renew && $subscription->next_billing_date) {
            $facts[] = ['label' => 'Next billing', 'value' => $this->formatDate($subscription->next_billing_date)];
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
