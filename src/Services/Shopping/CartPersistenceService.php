<?php

namespace App\Services\Shopping;

use App\Framework\Session\Session;
use App\Models\CartSnapshot;

/**
 * CartPersistenceService
 *
 * Handles cart persistence during OTP authentication flow
 * Solves the problem: Session expires during OTP flow → cart lost
 *
 * Strategy: Store cart data in database with session_id + email
 * When member authenticates, restore cart from database
 */
class CartPersistenceService
{
    /**
     * Save cart snapshot before OTP flow
     *
     * Called when existing member email is detected
     * Stores cart items in database with temporary session token
     */
    public function snapshotCartForOTP(string $email, string $sessionId, int $siteId): string
    {
        // Generate unique token for this checkout session
        $checkoutToken = $this->generateToken();

        // Get current cart items from session
        $cartItems = Session::get('cart_items', []);

        if (empty($cartItems)) {
            return $checkoutToken;
        }

        // Store in database using model
        CartSnapshot::create([
            'email' => $email,
            'session_id' => $sessionId,
            'checkout_token' => $checkoutToken,
            'site_id' => $siteId,
            'cart_data' => json_encode($cartItems),
            'expires_at' => now_datetime()->modify('+30 minutes')->format('Y-m-d H:i:s'),
            'created_at' => now_datetime()->format('Y-m-d H:i:s')
        ]);

        return $checkoutToken;
    }

    /**
     * Generate unique checkout token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Restore cart after successful OTP authentication
     *
     * Called after OTP verification succeeds
     * Merges database cart with current session cart
     */
    public function restoreCartAfterAuth(string $email, int $siteId): bool
    {
        $snapshot = CartSnapshot::where('email', $email)
            ->where('site_id', $siteId)
            ->where('expires_at', '>', now_datetime()->format('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC')
            ->first();

        if (!$snapshot) {
            return false;
        }

        $cartData = $snapshot->getCartData();

        if (empty($cartData)) {
            return false;
        }

        // Restore to session
        Session::put('cart', $cartData);
        Session::put('checkout_token', $snapshot->checkout_token);

        // Clean up snapshot
        $snapshot->delete();

        return true;
    }

    /**
     * Get checkout token for current session
     *
     * Returns token if cart was snapshotted during OTP flow
     */
    public function getCheckoutToken(): ?string
    {
        return Session::get('checkout_token');
    }

    /**
     * Store checkout token in session
     *
     * Used to maintain continuity across session refreshes
     */
    public function setCheckoutToken(string $token): void
    {
        Session::put('checkout_token', $token);
    }

    /**
     * Clean up expired snapshots
     *
     * Should be run as scheduled task
     */
    public function cleanupExpiredSnapshots(): int
    {
        return CartSnapshot::where('expires_at', '<', now_datetime()->format('Y-m-d H:i:s'))
            ->delete();
    }

    /**
     * Get cart item count from snapshot
     *
     * Useful for showing "You have X items waiting" message
     */
    public function getSnapshotItemCount(string $email, int $siteId): int
    {
        $snapshot = CartSnapshot::where('email', $email)
            ->where('site_id', $siteId)
            ->where('expires_at', '>', now_datetime()->format('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC')
            ->first();

        if (!$snapshot) {
            return 0;
        }

        return $snapshot->getItemCount();
    }
}