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

        foreach (['current', 'action_required', 'previous'] as $group) {
            $grouped[$group] = array_map(
                fn (array $subscription): array => $this->present($subscription, $context),
                $grouped[$group] ?? [],
            );
        }

        $plans = $context->canAcquireSubscription && $siteId !== null
            ? $this->planService->getActivePlansForSite($siteId)
            : [];

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
            'account_context' => $context->toArray(),
            'plans' => $plans,
            'subscription_modal_data' => $context->showSubscriptionModal ? [
                'plans' => $plans,
                'show_modal' => false,
                'is_direct' => true,
            ] : [],
        ];
    }

    private function present(array $subscription, SubscriptionAccountContext $context): array
    {
        $site = isset($subscription['site_id']) ? Site::find((int) $subscription['site_id']) : null;
        $subscription['site_name'] = $site?->name;
        $subscription['site_slug'] = $site?->slug;

        if ($context->mode !== 'member' || $context->siteSlug === null) {
            return $subscription;
        }

        $globalBase = '/press-stack/account/subscriptions/' . $subscription['id'];
        $siteBase = '/' . $context->siteSlug . '/member/subscriptions/' . $subscription['id'];

        return $this->mapUrls($subscription, $globalBase, $siteBase);
    }

    private function mapUrls(array $payload, string $globalBase, string $siteBase): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->mapUrls($value, $globalBase, $siteBase);
            } elseif (is_string($value) && str_starts_with($value, $globalBase)) {
                $payload[$key] = $siteBase . substr($value, strlen($globalBase));
            }
        }

        return $payload;
    }
}
