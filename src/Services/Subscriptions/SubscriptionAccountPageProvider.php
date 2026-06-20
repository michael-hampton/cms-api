<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionCancellationReason;
use App\Models\Site;

final readonly class SubscriptionAccountPageProvider
{
    public function __construct(
        private SubscriptionListingService $listingService,
        private SubscriptionPlanService $planService,
        private SubscriptionAccountFaqProvider $faqProvider,
    ) {
    }

    public function forMember(int $memberId, ?int $siteId, SubscriptionAccountContext $context): array
    {
        if ($context->isSiteScoped && $siteId === null) {
            throw new \InvalidArgumentException('A site-scoped account requires a site ID.');
        }

        if (!$context->isSiteScoped && $siteId !== null) {
            throw new \InvalidArgumentException('A global account cannot receive a site ID.');
        }

        $grouped = $this->listingService->getGroupedSubscriptions($memberId, $siteId);
        $sites = $context->isSiteScoped ? [] : $this->loadSites($grouped);
        $endpointProvider = $context->mode === 'member'
            ? new MemberSubscriptionAccountEndpointProvider((string) $context->siteSlug)
            : new PressStackSubscriptionAccountEndpointProvider();

        foreach (['current', 'action_required', 'previous'] as $group) {
            $grouped[$group] = array_map(
                fn (array $subscription): array => $this->present(
                    $subscription,
                    $context,
                    $endpointProvider,
                    $sites,
                ),
                $grouped[$group] ?? [],
            );
        }

        $plans = $context->canAcquireSubscription && $siteId !== null
            ? $this->planService->getActivePlansForSite($siteId)
            : [];

        $accountContext = $context->toArray();
        $accountContext['cancel_endpoint_template'] = $context->mode === 'member'
            ? '/' . $context->siteSlug . '/member/subscriptions/unified/__SUBSCRIPTION_ID__/cancel'
            : '/press-stack/account/subscriptions/__SUBSCRIPTION_ID__/cancel';

        return [
            'grouped' => $grouped,
            'summary' => $this->listingService->getSubscriptionSummary($memberId, $siteId),
            'cancellation_reasons' => array_map(
                static fn (SubscriptionCancellationReason $reason): array => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                ],
                SubscriptionCancellationReason::cases(),
            ),
            'faqs' => $this->faqProvider->all(),
            'account_context' => $accountContext,
            'plans' => $plans,
            'subscription_modal_data' => $context->showSubscriptionModal ? [
                'plans' => $plans,
                'show_modal' => false,
                'is_direct' => true,
            ] : [],
        ];
    }

    private function present(
        array $subscription,
        SubscriptionAccountContext $context,
        SubscriptionAccountEndpointProviderInterface $endpointProvider,
        array $sites,
    ): array {
        $subscriptionId = (int) $subscription['id'];
        $endpoints = $endpointProvider->forId($subscriptionId);

        if ($context->isSiteScoped) {
            $subscription['site_name'] = $context->site?->name;
            $subscription['site_slug'] = $context->siteSlug;
            $subscription['account_management'] = array_merge(
                $subscription['account_management'] ?? [],
                $endpoints,
            );
            $subscription['billing_date_preview_endpoint'] = $endpoints['billing_date_preview_endpoint'];
            $subscription['billing_date_update_endpoint'] = $endpoints['billing_date_update_endpoint'];
            $subscription['actions'] = $this->memberActions($subscription['actions'] ?? [], $endpoints);

            return $subscription;
        }

        $site = $sites[(int) ($subscription['site_id'] ?? 0)] ?? null;
        $subscription['site_name'] = $site?->name;
        $subscription['site_slug'] = $site?->slug;

        return $subscription;
    }

    private function memberActions(array $actions, array $endpoints): array
    {
        foreach ($actions as &$action) {
            $key = $action['key'] ?? null;

            if ($key === 'pause') {
                $action['endpoint'] = $endpoints['pause_endpoint'];
            } elseif ($key === 'resume') {
                $action['endpoint'] = $endpoints['resume_endpoint'];
            } elseif ($key === 'renew') {
                $action['url'] = $endpoints['renew_url'];
            } elseif ($key === 'resubscribe') {
                $action['url'] = $endpoints['resubscribe_url'];
            } elseif ($key === 'settle_payment') {
                $action['url'] = $endpoints['settle_payment_url'];
            }
        }
        unset($action);

        return $actions;
    }

    private function loadSites(array $grouped): array
    {
        $siteIds = [];

        foreach (['current', 'action_required', 'previous'] as $group) {
            foreach ($grouped[$group] ?? [] as $subscription) {
                if (!empty($subscription['site_id'])) {
                    $siteIds[] = (int) $subscription['site_id'];
                }
            }
        }

        $siteIds = array_values(array_unique($siteIds));
        if ($siteIds === []) {
            return [];
        }

        $sites = [];
        foreach (Site::whereIn('id', $siteIds)->get() as $site) {
            $sites[(int) $site->id] = $site;
        }

        return $sites;
    }
}
