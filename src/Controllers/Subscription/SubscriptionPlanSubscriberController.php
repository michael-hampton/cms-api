<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Resources\MemberSubscriptionPreferenceResource;
use App\Resources\SubscriptionResource;

/**
 * Handles read-only endpoints that expose subscriber data scoped to a plan.
 *
 * Routes (register under your site-scoped middleware group):
 *   GET  /api/{site}/subscriptions/plans/{planId}/subscribers
 *   GET  /api/{site}/subscriptions/{subscriptionId}
 *   GET  /api/{site}/subscriptions/{subscriptionId}/preferences
 */
class SubscriptionPlanSubscriberController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository                 $subscriptionRepository,
        private readonly MemberSubscriptionPreferenceRepository $preferenceRepository,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /api/{site}/subscriptions/plans/{planId}/subscribers
    // =========================================================================

    /**
     * Paginated list of all subscriptions (with member) for a given plan.
     *
     * Query params:
     *   page     int  default 1
     *   per_page int  default 25, max 100
     *   status   string  optional — filters by subscription status
     */
    public function planSubscribers(Request $request, string $site, int $planId): mixed
    {
        try {
            $page = max(1, (int)($request->get('page', 1)));
            $perPage = min(100, max(1, (int)($request->get('per_page', 25))));
            $status = $request->get('status');

            $result = $this->subscriptionRepository->getSubscribersForPlan(
                $planId,
                $page,
                $perPage,
                $status ?: null,
            );

            $items = array_map(
                fn($sub) => (new SubscriptionResource($sub))->toArray(),
                $result['items']->all(),
            );

            return $this->resourceResponse([
                'success' => true,
                'items' => $items,
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // GET /api/{site}/subscriptions/{subscriptionId}
    // =========================================================================

    /**
     * Full detail for a single subscription (member + plan eager-loaded).
     */
    public function show(Request $request, string $site, int $subscriptionId): mixed
    {
        try {
            $siteId = SiteContext::getId();

            $subscription = $this->subscriptionRepository->find($subscriptionId, ['member', 'plan']);

            if (!$subscription) {
                return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'subscription' => (new SubscriptionResource($subscription))->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // GET /api/{site}/subscriptions/{subscriptionId}/preferences
    // =========================================================================

    /**
     * Return the subscription-preference record for the member who owns this
     * subscription.  The preference is keyed by member + site, so we resolve
     * the member_id from the subscription rather than asking the caller to
     * supply it (preventing enumeration of other members' preferences).
     */
    public function preferences(Request $request, string $site, int $subscriptionId): mixed
    {
        try {
            $siteId = SiteContext::getId();

            $subscription = $this->subscriptionRepository->find($subscriptionId, ['member', 'plan']);

            if (!$subscription) {
                return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
            }

            $preference = $this->preferenceRepository->findByMember($subscription->member_id, $siteId);

            return $this->resourceResponse([
                'success' => true,
                'preference' => $preference
                    ? (new MemberSubscriptionPreferenceResource($preference))->toArray()
                    : null,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}