<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Billing\PaymentMethodDto;
use DateTime;

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
            if ($this->isPaymentMethodExpired($method)) {
                $warnings[] = [
                    'payment_method' => $method,
                    'status' => 'expired',
                    'message' => 'This card has expired and needs to be updated',
                ];
            } elseif ($this->isPaymentMethodExpiring($method)) {
                $warnings[] = [
                    'payment_method' => $method,
                    'status' => 'expiring',
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
