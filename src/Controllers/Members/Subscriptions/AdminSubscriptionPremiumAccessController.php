<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Exceptions\Subscriptions\PremiumAccessAlreadyGrantedException;
use App\Exceptions\Subscriptions\PremiumAccessNotFoundException;
use App\Exceptions\Subscriptions\SubscriptionNotFoundException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\SubscriptionPremiumAccessService;

class AdminSubscriptionPremiumAccessController extends Controller
{
    public function __construct(
        private readonly SubscriptionPremiumAccessService $premiumAccessService,
        private readonly Auth                             $auth
    )
    {
        parent::__construct();
    }

    public function index(Request $request, int $subscriptionId)
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            $accesses = $this->premiumAccessService->getForSubscription($subscriptionId);

            return $this->jsonResponse([
                'success' => true,
                'accesses' => $accesses
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function grant(Request $request, int $subscriptionId)
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $premiumType = $request->input('premium_type');
        $premiumIdentifier = $request->input('premium_identifier');
        $expiresAt = $request->input('expires_at');

        if (!$premiumType || !$premiumIdentifier) {
            return $this->jsonResponse([
                'error' => 'premium_type and premium_identifier are required'
            ], 422);
        }

        try {
            $access = $this->premiumAccessService->grant(
                $subscriptionId,
                $premiumType,
                $premiumIdentifier,
                $expiresAt
            );

            Logger::info('Premium access granted manually by admin', [
                'subscription_id' => $subscriptionId,
                'premium_type' => $premiumType,
                'premium_identifier' => $premiumIdentifier,
                'admin_id' => $this->auth->id(),
            ]);

            return $this->jsonResponse(['success' => true, 'access' => $access]);

        } catch (SubscriptionNotFoundException $e) {
            return $this->jsonResponse(['error' => 'Subscription not found'], 404);
        } catch (PremiumAccessAlreadyGrantedException $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function revoke(Request $request, int $subscriptionId)
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $premiumType = $request->input('premium_type');
        $premiumIdentifier = $request->input('premium_identifier');

        if (!$premiumType || !$premiumIdentifier) {
            return $this->jsonResponse([
                'error' => 'premium_type and premium_identifier are required'
            ], 422);
        }

        try {
            $this->premiumAccessService->revoke($subscriptionId, $premiumType, $premiumIdentifier);

            Logger::info('Premium access revoked manually by admin', [
                'subscription_id' => $subscriptionId,
                'premium_type' => $premiumType,
                'premium_identifier' => $premiumIdentifier,
                'admin_id' => $this->auth->id(),
            ]);

            return $this->jsonResponse(['success' => true]);

        } catch (SubscriptionNotFoundException $e) {
            return $this->jsonResponse(['error' => 'Subscription not found'], 404);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            $access = $this->premiumAccessService->update($id, $request->all() ?? []);

            Logger::info('Premium access updated manually by admin', [
                'access_id' => $access->id,
                'subscription_id' => $access->subscription_id,
                'admin_id' => $this->auth->id(),
            ]);

            return $this->jsonResponse(['success' => true, 'access' => $access]);

        } catch (PremiumAccessNotFoundException $e) {
            return $this->jsonResponse(['error' => 'Premium access not found'], 404);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        if (!$this->auth->check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        try {
            $this->premiumAccessService->delete($id);

            Logger::info('Premium access deleted manually by admin', [
                'access_id' => $id,
                'admin_id' => $this->auth->id(),
            ]);

            return $this->jsonResponse(['success' => true]);

        } catch (PremiumAccessNotFoundException $e) {
            return $this->jsonResponse(['error' => 'Premium access not found'], 404);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}