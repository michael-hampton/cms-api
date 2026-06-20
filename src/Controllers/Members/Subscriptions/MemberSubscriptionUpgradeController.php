<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Services\Subscriptions\SubscriptionUpgradeService;

class MemberSubscriptionUpgradeController extends Controller
{
    public function __construct(
        private readonly SubscriptionUpgradeService $upgradeService
    ) {
        parent::__construct();
    }

    public function index(int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $premiumType = $_GET['premium_type'] ?? null;
        $premiumIdentifier = $_GET['premium_identifier'] ?? null;

        try {
            $upgradeInfo = $this->upgradeService->getUpgradeOptions(
                $subscriptionId,
                $premiumType,
                $premiumIdentifier
            );

            return $this->view('member/subscriptions/upgrade', [
                'member' => $member,
                'site' => SiteContext::get(),
                'upgradeInfo' => $upgradeInfo,
                'subscriptionId' => $subscriptionId,
                'premiumType' => $premiumType,
                'premiumIdentifier' => $premiumIdentifier,
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to load upgrade options', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            $_SESSION['flash_error'] = 'Unable to load upgrade options';
            return $this->notFound();
        }
    }

    public function preview(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $upgradePlanId = (int) $request->input('upgrade_plan_id');

        try {
            return $this->resourceResponse([
                'success' => true,
                'data' => $this->upgradeService->previewUpgrade($subscriptionId, $upgradePlanId),
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function upgrade(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $upgradePlanId = (int) $request->input('upgrade_plan_id');
        $confirmationReference = $request->input('payment_intent_id');

        if ($confirmationReference !== null && !is_string($confirmationReference)) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Confirmation reference must be a string.',
            ], 422);
        }

        try {
            $result = $this->upgradeService->upgradeSubscription(
                $subscriptionId,
                $upgradePlanId,
                [
                    'payment_method_id' => $request->input('payment_method_id'),
                    'payment_intent_id' => $confirmationReference,
                    'member' => $member,
                ]
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'subscription' => $result['subscription'],
                    'price_charged' => $result['price_charged'],
                    'requires_confirmation' => (bool) ($result['requires_confirmation'] ?? false),
                    'payment_intent_id' => $result['payment_result']['payment_intent_id'] ?? null,
                    'client_secret' => $result['payment_result']['client_secret'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Logger::error('Subscription upgrade failed', [
                'subscription_id' => $subscriptionId,
                'upgrade_plan_id' => $upgradePlanId,
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
