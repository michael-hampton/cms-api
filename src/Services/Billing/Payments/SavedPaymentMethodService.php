<?php

namespace App\Services\Billing\Payments;

use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;

/**
 * @deprecated Use StripeCustomerPaymentMethodService directly.
 *
 * Compatibility alias only; there is now one payment-method implementation.
 */
class_alias(StripeCustomerPaymentMethodService::class, __NAMESPACE__ . '\\SavedPaymentMethodService');
