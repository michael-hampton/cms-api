<?php

namespace App\Services\Subscriptions;

use App\Models\CancellationReason;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\SubscriptionAccountModalPlanRepository;
use App\Repositories\Subscriptions\SubscriptionAccountSiteRepository;

final readonly class SubscriptionAccountPageProvider
{
    public function __construct(
        private SubscriptionListingService $listingService,
        private SubscriptionAccountFaqProvider $faqProvider,
        private SubscriptionAccountModalPlanRepository $modalPlanRepository,
        private SubscriptionAccountSiteRepository $siteRepository,
        private CancellationReasonRepository $cancellationReasonRepository,
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

        $plans = $this->plansForModal($grouped, $context, $siteId);

        $accountContext = $context->toArray();
        $accountContext['cancel_endpoint_template'] = $context->mode === 'member'
            ? '/' . $context->siteSlug . '/member/subscriptions/unified/__SUBSCRIPTION_ID__/cancel'
            : '/press-stack/account/subscriptions/__SUBSCRIPTION_ID__/cancel';

        return [
            'grouped' => $grouped,
            'summary' => $this->listingService->getSubscriptionSummary($memberId, $siteId),
            'cancellation_reasons' => array_map(
                static fn (CancellationReason $reason): array => [
                    'value' => $reason->code,
                    'label' => $reason->label,
                    'requires_note' => (bool) $reason->requires_note,
                ],
                $this->cancellationReasonRepository->listActive()->all(),
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
        $subscription['account_management'] = array_merge(
            $subscription['account_management'] ?? [],
            $endpoints,
        );
        $subscription['billing_date_preview_endpoint'] = $endpoints['billing_date_preview_endpoint'];
        $subscription['billing_date_update_endpoint'] = $endpoints['billing_date_update_endpoint'];
        $subscription['payment_method_endpoint'] = $endpoints['payment_method_endpoint'];
        $subscription['payment_methods_list_endpoint'] = $endpoints['payment_methods_list_endpoint'];
        $subscription['payment_methods_page_url'] = $endpoints['payment_methods_page_url'];

        $pauseFlow = $subscription['account_management']['pause_flow'] ?? null;
        if (is_array($pauseFlow)) {
            $pauseFlow['endpoint'] = $endpoints['pause_endpoint'];
            $subscription['account_management']['pause_flow'] = $pauseFlow;
            $subscription['pause_flow'] = $pauseFlow;
        } else {
            $subscription['pause_flow'] = null;
        }

        if (is_array($subscription['cancellation_flow'] ?? null)) {
            $subscription['cancellation_flow']['endpoint'] = $endpoints['cancel_endpoint'];
        }

        $subscription['actions'] = $this->contextualActions(
            $subscription['actions'] ?? [],
            $endpoints,
        );

        if ($context->isSiteScoped) {
            $subscription['site_name'] = $context->site?->name;
            $subscription['site_slug'] = $context->siteSlug;

            return $subscription;
        }

        $site = $sites[(int) ($subscription['site_id'] ?? 0)] ?? null;
        $subscription['site_name'] = $site?->name;
        $subscription['site_slug'] = $site?->slug;

        return $subscription;
    }

    private function contextualActions(array $actions, array $endpoints): array
    {
        foreach ($actions as &$action) {
            $key = $action['key'] ?? null;

            if ($key === 'pause') {
                $action['label'] = 'Pause subscription';
                $action['type'] = 'modal';
                $action['modal'] = 'pause_subscription';
                $action['endpoint'] = $endpoints['pause_endpoint'];
            } elseif ($key === 'resume') {
                $action['endpoint'] = $endpoints['resume_endpoint'];
            } elseif ($key === 'reactivate') {
                $action['endpoint'] = $endpoints['reactivate_endpoint'];
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

    private function plansForModal(array $grouped, SubscriptionAccountContext $context, ?int $siteId): iterable
    {
        if (!$context->canAcquireSubscription) {
            return [];
        }

        $sourcePlanIds = $this->resubscribePlanIds($grouped);

        if ($context->isSiteScoped && $siteId !== null) {
            return $this->modalPlanRepository->findForAccountModal([$siteId], $sourcePlanIds);
        }

        return $this->modalPlanRepository->findForAccountModal([], $sourcePlanIds);
    }

    private function resubscribePlanIds(array $grouped): array
    {
        $planIds = [];

        foreach (['current', 'action_required', 'previous'] as $group) {
            foreach ($grouped[$group] ?? [] as $subscription) {
                foreach ($subscription['actions'] ?? [] as $action) {
                    if (($action['key'] ?? null) === 'resubscribe' && !empty($subscription['plan_id'])) {
                        $planIds[] = (int) $subscription['plan_id'];
                    }
                }
            }
        }

        return $planIds;
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

        return $this->siteRepository->findByIdsIndexed($siteIds);
    }
}