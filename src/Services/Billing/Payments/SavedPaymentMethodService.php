<?php

namespace App\Services\Billing\Payments;

use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;

/**
 * @deprecated Use StripeCustomerPaymentMethodService directly.
 *
 * Compatibility shim only. Payment-method behaviour lives in the canonical
 * StripeCustomerPaymentMethodService implementation.
 */
final class SavedPaymentMethodService extends StripeCustomerPaymentMethodService
{
}
