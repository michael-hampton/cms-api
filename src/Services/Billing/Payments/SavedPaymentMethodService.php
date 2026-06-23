<?php

namespace App\Services\Billing\Payments;

use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;

/**
 * @deprecated Use StripeCustomerPaymentMethodService directly.
 *
 * Kept as a thin compatibility shim while older constructor wiring is removed.
 * All Stripe payment-method behaviour now lives in StripeCustomerPaymentMethodService.
 */
class SavedPaymentMethodService extends StripeCustomerPaymentMethodService
{
}
