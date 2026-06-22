<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Newsletter;
use App\Models\Site;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class SubscriptionListingService
{
    private SubscriptionAccountStateResolver $stateResolver;
    private SubscriptionContinuationResolver $continuationResolver;
    private SubscriptionCancellationFlowProvider $cancellationFlowProvider;
    private SubscriptionPaymentRecoveryService $paymentRecoveryService;
    private SubscriptionAccountManagementProvider $accountManagementProvider;
    private SubscriptionAccountEndpointProviderInterface $endpointProvider;

    /** @var array<int, ?string> */
    private array $siteSlugCache = [];

    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly NewsletterRepository   $newsletterRepository,
        ?SubscriptionAccountStateResolver $stateResolver = null,
        ?SubscriptionContinuationResolver $continuationResolver = null,
        ?SubscriptionCancellationFlowProvider $cancellationFlowProvider = null,
        ?SubscriptionPaymentRecoveryService $paymentRecoveryService = null,
        ?SubscriptionAccountManagementProvider $accountManagementProvider = null,
        ?SubscriptionAccountEndpointProviderInterface $endpointProvider = null,
    ) {
        $this->stateResolver = $stateResolver ?? new SubscriptionAccountStateResolver();
        $this->continuationResolver = $continuationResolver ?? new SubscriptionContinuationResolver();
        $this->cancellationFlowProvider = $cancellationFlowProvider ?? new SubscriptionCancellationFlowProvider();
        $this->paymentRecoveryService = $paymentRecoveryService ?? new SubscriptionPaymentRecoveryService();
        $this->endpointProvider = $endpointProvider ?? new PressStackSubscriptionAccountEndpointProvider();
        $this->accountManagementProvider = $accountManagementProvider
            ?? new SubscriptionAccountManagementProvider($this->endpointProvider);
    }

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

        $this->primeSiteSlugCache($subscriptions);

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

            $status = $formatted['display_state']['group'] === 'previous'
                ? 'expired'
                : 'active';
            $type = $subscription->isPrint()
                ? SubscriptionType::PRINTED->value
                : SubscriptionType::DIGITAL->value;
            $grouped[$status][$type][] = $formatted;
        }

        return $grouped;
    }

    public function formatSubscriptionForListing(Subscription $subscription): array
    {
        $newsletters = $this->getAccessibleNewsletters($subscription);
        $displayState = $this->stateResolver->resolve($subscription);
        $allowedActionKeys = $this->allowedActionKeysForState($displayState);
        $continuation = $this->continuationResolver->resolve($subscription, $displayState);
        $cancellationFlow = $this->cancellationFlowProvider->for($subscription);
        $paymentRecovery = $this->paymentRecoveryService->getListingData($subscription);
        $siteSlug = $this->siteSlug($subscription);
        $endpoints = $this->endpointProvider->forId((int) $subscription->id);
        $actions = [];

        if ($siteSlug && in_array('manage', $allowedActionKeys, true)) {
            $actions[] = [
                'key' => 'manage',
                'label' => 'Manage',
                'type' => 'redirect',
                'url' => "/{$siteSlug}/member/subscriptions",
                'tone' => 'primary',
            ];
        }

        if ((string) $subscription->status === 'paused') {
            if (in_array('resume', $allowedActionKeys, true)) {
                $actions[] = [
                    'key' => 'resume',
                    'label' => 'Resume',
                    'type' => 'api',
                    'method' => 'POST',
                    'endpoint' => $endpoints['resume_endpoint'],
                    'tone' => 'commercial',
                ];
            }
        } elseif (
            in_array('pause', $allowedActionKeys, true)
            && $subscription->isActive()
            && !$subscription->isCancellationScheduled()
        ) {
            $actions[] = [
                'key' => 'pause',
                'label' => 'Pause',
                'type' => 'api',
                'method' => 'POST',
                'endpoint' => $endpoints['pause_endpoint'],
                'tone' => 'secondary',
            ];
        }

        if ($cancellationFlow && in_array('cancel', $allowedActionKeys, true)) {
            $cancellationFlow['endpoint'] = $endpoints['cancel_endpoint'];
            $actions[] = [
                'key' => 'cancel',
                'label' => $cancellationFlow['action_label'],
                'type' => 'modal',
                'modal' => 'cancel',
                'tone' => 'secondary',
            ];
        }

        if ($continuation && in_array((string) ($continuation['key'] ?? ''), $allowedActionKeys, true)) {
            $actions[] = $this->contextualiseContinuationAction($continuation, $endpoints);
        }

        if ($paymentRecovery && in_array('settle_payment', $allowedActionKeys, true)) {
            $actions[] = [
                'key' => 'settle_payment',
                'label' => 'Settle ' . $paymentRecovery['amount'],
                'type' => 'redirect',
                'url' => $endpoints['settle_payment_url'],
                'tone' => 'commercial',
            ];
        }

        $type = $subscription->isPrint()
            ? SubscriptionType::PRINTED->value
            : SubscriptionType::DIGITAL->value;
        $facts = $this->facts($subscription, $displayState);

        return [
            'id' => $subscription->id,
            'site_id' => $subscription->site_id,
            'plan_name' => $subscription->plan_name,
            'title' => $subscription->plan_name,
            'type' => $type,
            'access_type' => $type,
            'price' => $subscription->price,
            'currency' => $subscription->currency,
            'plan_descriptor' => $subscription->plan?->billing_period ?? null,
            'next_issue_date' => $this->formatDate($subscription->next_issue_date ?? null),
            'status' => $subscription->status,
            'is_active' => $subscription->isActive(),
            'is_current' => $displayState['group'] === 'current',
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
            'facts' => $facts,
            'benefits' => $this->benefits($newsletters, $this->getArchiveUrl($subscription)),
            'actions' => $actions,
            'cancellation_flow' => $cancellationFlow,
            'payment_recovery' => $paymentRecovery,
            'management_links' => $this->managementLinks($subscription, $siteSlug),
            'can_manage_billing_date' => $subscription->hasStripeSubscription()
                && $subscription->auto_renew
                && $subscription->status === 'active',
            'billing_day_of_month' => $subscription->billing_day_of_month
                ?? $subscription->next_billing_date?->format('j'),
            'billing_date_preview_endpoint' => $endpoints['billing_date_preview_endpoint'],
            'billing_date_update_endpoint' => $endpoints['billing_date_update_endpoint'],
            'account_management' => array_merge(
                $this->accountManagementProvider->for($subscription, $displayState),
                ['facts' => $facts],
            ),
        ];
    }

    private function allowedActionKeysForState(array $displayState): array
    {
        return match ($displayState['key'] ?? null) {
            'renewing_soon' => ['manage'],
            'expiring_soon' => ['manage', 'renew'],
            'renewal_offer_accepted' => ['manage', 'view_offer'],
            'expired' => ['manage', 'reactivate'],
            'cancelled' => ['manage', 'resubscribe'],
            default => ['manage', 'pause', 'resume', 'cancel', 'settle_payment', 'reactivate', 'renew', 'resubscribe'],
        };
    }

    private function contextualiseContinuationAction(array $action, array $endpoints): array
    {
        return match ($action['key'] ?? null) {
            'reactivate' => array_merge($action, ['endpoint' => $endpoints['reactivate_endpoint']]),
            'renew' => array_merge($action, ['url' => $endpoints['renew_url']]),
            'resubscribe' => array_merge($action, ['url' => $endpoints['resubscribe_url']]),
            default => $action,
        };
    }

    private function managementLinks(Subscription $subscription, ?string $siteSlug): array
    {
        if (!$siteSlug) {
            return [];
        }

        $links = [
            [
                'key' => 'subscription_management',
                'label' => 'Subscription settings',
                'url' => "/{$siteSlug}/member/subscriptions",
            ],
            [
                'key' => 'email_preferences',
                'label' => 'Email preferences',
                'url' => "/{$siteSlug}/member/subscriptions/preferences",
            ],
        ];

        if ($subscription->isPrint()) {
            $links[] = [
                'key' => 'delivery_schedule',
                'label' => 'Issue delivery schedule',
                'url' => "/{$siteSlug}/member/subscriptions/{$subscription->id}/issue-deliveries",
            ];
            $links[] = [
                'key' => 'delivery_address',
                'label' => 'Delivery address',
                'url' => "/{$siteSlug}/member/addresses",
            ];
        }

        if ($subscription->isActive()) {
            $links[] = [
                'key' => 'upgrade',
                'label' => 'Upgrade options',
                'url' => "/{$siteSlug}/member/subscriptions/{$subscription->id}/upgrade",
            ];
        }

        if (!empty($subscription->download_url)) {
            $links[] = [
                'key' => 'digital_download',
                'label' => 'Download digital edition',
                'url' => (string) $subscription->download_url,
            ];
        }

        return $links;
    }

    private function primeSiteSlugCache(iterable $subscriptions): void
    {
        $siteIds = [];

        foreach ($subscriptions as $subscription) {
            $siteId = (int) ($subscription->site_id ?? 0);
            if ($siteId > 0) {
                $siteIds[$siteId] = true;
            }
        }

        $siteIds = array_keys($siteIds);
        if ($siteIds === []) {
            return;
        }

        foreach ($siteIds as $siteId) {
            $this->siteSlugCache[$siteId] = null;
        }

        foreach (Site::whereIn('id', $siteIds)->get() as $site) {
            $slug = $site->slug;
            $this->siteSlugCache[(int) $site->id] = is_string($slug) && $slug !== ''
                ? $slug
                : null;
        }
    }

    private function siteSlug(Subscription $subscription): ?string
    {
        $siteId = (int) $subscription->site_id;

        if (array_key_exists($siteId, $this->siteSlugCache)) {
            return $this->siteSlugCache[$siteId];
        }

        $site = Site::find($siteId);
        $slug = $site?->slug;
        $this->siteSlugCache[$siteId] = is_string($slug) && $slug !== '' ? $slug : null;

        return $this->siteSlugCache[$siteId];
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

        if ($subscription->isPrint()) {
            $nextIssueTimestamp = $this->timestamp($subscription->next_issue_date ?? null);
            $endTimestamp = $this->timestamp($subscription->end_date ?? null);

            if ($nextIssueTimestamp !== null && ($endTimestamp === null || $nextIssueTimestamp <= $endTimestamp)) {
                $facts[] = [
                    'label' => 'Next issue',
                    'value' => $this->formatDate($subscription->next_issue_date),
                ];
            }
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

    private function timestamp(mixed $date): ?int
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->getTimestamp();
        }

        if (is_string($date) && $date !== '') {
            $timestamp = strtotime($date);
            return $timestamp === false ? null : $timestamp;
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

    private function canRenew(Subscription $subscription): bool
    {
        if ($subscription->status === 'expired') {
            return true;
        }

        if ($subscription->status === 'cancelled') {
            return true;
        }

        if ($subscription->end_date) {
            $daysUntilExpiry = $subscription->end_date->diff(new \DateTime())->days;
            return $daysUntilExpiry <= 30;
        }

        return false;
    }

    private function shouldShowRenewCTA(Subscription $subscription): bool
    {
        if (!$this->canRenew($subscription)) {
            return false;
        }

        if ($subscription->auto_renew && $subscription->isActive()) {
            return false;
        }

        return true;
    }

    private function getArchiveUrl(Subscription $subscription): ?string
    {
        if ($subscription->isDigital() || $subscription->includes_digital_access) {
            return '/newsletters/archive';
        }

        return null;
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

    public function getSubscriptionSummary(int $memberId, ?int $siteId = null): array
    {
        $subscriptions = Subscription::where('member_id', $memberId)
            ->when($siteId !== null, function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->get();

        $states = $subscriptions->map(fn ($subscription) => $this->stateResolver->resolve($subscription));
        $active = $subscriptions->filter(fn ($subscription) => $subscription->isActive())->count();
        $current = $states->filter(fn ($state) => $state['group'] === 'current')->count();
        $actionRequired = $states->filter(fn ($state) => $state['group'] === 'action_required')->count();
        $expired = $states->filter(fn ($state) => $state['key'] === 'expired')->count();
        $cancelled = $states->filter(fn ($state) => $state['key'] === 'cancelled')->count();

        return [
            'total' => $subscriptions->count(),
            'active' => $active,
            'current' => $current,
            'action_required' => $actionRequired,
            'previous' => $states->filter(fn ($state) => $state['group'] === 'previous')->count(),
            'expired' => $expired,
            'cancelled' => $cancelled,
        ];
    }
}
