<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionUpgradeService;
use Throwable;

final class ShopAccountSubscriptionUpgradeController extends Controller
{
    public function __construct(
        private readonly SubscriptionUpgradeService $upgradeService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function options(int $id): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->ownsSubscription($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            return $this->jsonResponse([
                'success' => true,
                'upgrade' => $this->upgradeService->getUpgradeOptions($id),
            ]);
        } catch (Throwable $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function preview(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->ownsSubscription($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $upgradePlanId = $this->positiveInteger($request->input('upgrade_plan_id'));
        if ($upgradePlanId === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'A valid upgrade plan is required.'], 422);
        }

        try {
            return $this->jsonResponse([
                'success' => true,
                'preview' => $this->upgradeService->previewUpgrade($id, $upgradePlanId),
            ]);
        } catch (Throwable $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function upgrade(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->ownsSubscription($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $upgradePlanId = $this->positiveInteger($request->input('upgrade_plan_id'));
        if ($upgradePlanId === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'A valid upgrade plan is required.'], 422);
        }

        $intentId = $request->input('payment_intent_id');
        if ($intentId !== null && !is_string($intentId)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Payment confirmation reference must be a string.'], 422);
        }

        try {
            $result = $this->upgradeService->upgradeSubscription($id, $upgradePlanId, [
                'payment_method_id' => $request->input('payment_method_id'),
                'payment_intent_id' => $intentId,
                'member' => $member,
            ]);

            return $this->jsonResponse([
                'success' => true,
                'message' => $result['message'],
                'requires_confirmation' => (bool) ($result['requires_confirmation'] ?? false),
                'price_charged' => $result['price_charged'],
                'payment_intent_id' => $result['payment_result']['payment_intent_id'] ?? null,
                'client_secret' => $result['payment_result']['client_secret'] ?? null,
                'subscription' => $result['subscription'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function ownsSubscription(int $id, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($id);

        return $subscription && $subscription->member_id === $memberId;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            $integer = (int) $value;
            return $integer > 0 ? $integer : null;
        }

        return null;
    }
}
