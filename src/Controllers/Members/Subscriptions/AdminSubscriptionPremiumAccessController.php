<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Models\Subscription;

class AdminSubscriptionPremiumAccessController extends Controller
{
    /**
     * Grant premium access manually
     */
    public function grant(Request $request, int $subscriptionId)
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            return $this->jsonResponse(['error' => 'Subscription not found'], 404);
        }

        $premiumType = $request->input('premium_type'); // e.g., 'newsletter'
        $premiumIdentifier = $request->input('premium_identifier'); // e.g., 'insider'
        $expiresAt = $request->input('expires_at'); // Optional

        $expiryDate = $expiresAt ? new \DateTime($expiresAt) : null;

        $access = $subscription->grantPremiumAccess(
            $premiumType,
            $premiumIdentifier,
            $expiryDate
        );

        \App\Framework\Support\Logger::info('Premium access granted manually by admin', [
            'subscription_id' => $subscriptionId,
            'premium_type' => $premiumType,
            'premium_identifier' => $premiumIdentifier,
            'admin_id' => Auth::id()
        ]);

        return $this->jsonResponse([
            'success' => true,
            'access' => $access
        ]);
    }

    /**
     * Revoke premium access manually
     */
    public function revoke(Request $request, int $subscriptionId)
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
        }

        $subscription = Subscription::find($subscriptionId);

        if (!$subscription) {
            return $this->jsonResponse(['error' => 'Subscription not found'], 404);
        }

        $premiumType = $request->input('premium_type');
        $premiumIdentifier = $request->input('premium_identifier');

        $result = $subscription->revokePremiumAccess(
            $premiumType,
            $premiumIdentifier
        );

        \App\Framework\Support\Logger::info('Premium access revoked manually by admin', [
            'subscription_id' => $subscriptionId,
            'premium_type' => $premiumType,
            'premium_identifier' => $premiumIdentifier,
            'admin_id' => Auth::id()
        ]);

        return $this->jsonResponse([
            'success' => $result
        ]);
    }
}