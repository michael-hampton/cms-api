<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\Logger;

/**
 * Exposes Stripe configuration needed by admin UI components.
 *
 * The SetupIntent endpoint previously planned here is no longer needed:
 * admin subscription creation now goes through processCheckout() using a
 * payment_method_id the frontend collects via stripe.createPaymentMethod()
 * (same as the recurring checkout flow in subscription.php).
 */
class StripeConfigController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/admin/billing/stripe-config
     *
     * Returns the Stripe publishable key so the admin Angular app can
     * initialise Stripe.js for the subscription create drawer.
     *
     * Safe to expose to authenticated admin users.
     * Never exposes the secret key.
     */
    public function config(): mixed
    {
        if (!Auth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $publishableKey = $_ENV['STRIPE_PUBLIC_KEY']
            ?? config('payment.stripe.public_key')
            ?? '';

        if ($publishableKey === '') {
            Logger::error('STRIPE_PUBLIC_KEY is not configured');
            return $this->jsonResponse(['success' => false, 'message' => 'Stripe is not configured.'], 500);
        }

        return $this->resourceResponse([
            'success' => true,
            'publishable_key' => $publishableKey,
        ]);
    }
}