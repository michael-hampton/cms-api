<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Billing\PaymentMethodDto;
use App\Enums\Billing\PaymentMethodStatus;
use DateTime;

/**
 * Dedicated collaborator for deriving a payment method's presentation status
 * (active / expiring soon / expired). Kept separate from
 * StripeCustomerPaymentMethodService so the expiry-window calculation can
 * change independently of how payment methods are fetched or mutated.
 *
 * This is the single source of truth for the status shown by both the
 * PressStack account area and the site-scoped member area - the frontend
 * never re-derives this itself.
 */
class StripePaymentMethodWarningService
{
    /**
     * @param array{success?: bool, payment_methods?: PaymentMethodDto[]} $customerPaymentMethods
     */
    public function getPaymentMethodsWithWarnings(array $customerPaymentMethods): array
    {
        if (!($customerPaymentMethods['success'] ?? true)) {
            return $customerPaymentMethods;
        }

        $warnings = [];
        foreach ($customerPaymentMethods['payment_methods'] ?? [] as $method) {
            $status = $this->statusFor($method);

            if ($status === PaymentMethodStatus::Expired) {
                $warnings[] = [
                    'payment_method' => $method,
                    'status' => $status->value,
                    'message' => 'This card has expired and needs to be updated',
                ];
            } elseif ($status === PaymentMethodStatus::ExpiringSoon) {
                $warnings[] = [
                    'payment_method' => $method,
                    'status' => $status->value,
                    'message' => 'This card expires soon (' . $method->expMonth . '/' . $method->expYear . ')',
                ];
            }
        }

        return [
            ...$customerPaymentMethods,
            'warnings' => $warnings,
            'success' => count($warnings) === 0,
            'has_warnings' => $warnings !== [],
        ];
    }

    /**
     * Single source of truth for a payment method's lifecycle status.
     * Used by the shared API payload builder so both frontends render
     * from the same backend-provided value rather than inferring it.
     */
    public function statusFor(PaymentMethodDto $paymentMethod, int $monthsThreshold = 2): PaymentMethodStatus
    {
        if ($this->isPaymentMethodExpired($paymentMethod)) {
            return PaymentMethodStatus::Expired;
        }

        if ($this->isPaymentMethodExpiring($paymentMethod, $monthsThreshold)) {
            return PaymentMethodStatus::ExpiringSoon;
        }

        return PaymentMethodStatus::Active;
    }

    public function isPaymentMethodExpired(PaymentMethodDto $paymentMethod): bool
    {
        if ($paymentMethod->expMonth <= 0 || $paymentMethod->expYear <= 0) {
            return false;
        }

        $expiryDate = new DateTime("{$paymentMethod->expYear}-{$paymentMethod->expMonth}-01");
        $expiryDate->modify('last day of this month');

        return $expiryDate < new DateTime();
    }

    public function isPaymentMethodExpiring(PaymentMethodDto $paymentMethod, int $monthsThreshold = 2): bool
    {
        if ($paymentMethod->expMonth <= 0 || $paymentMethod->expYear <= 0) {
            return false;
        }

        $expiryDate = new DateTime("{$paymentMethod->expYear}-{$paymentMethod->expMonth}-01");
        $expiryDate->modify('last day of this month');

        $now = new DateTime();
        $threshold = (clone $now)->modify("+{$monthsThreshold} months");

        return $expiryDate <= $threshold && $expiryDate >= $now;
    }
}
