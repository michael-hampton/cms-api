<?php

namespace App\Services\Billing\Stripe;

use DateTime;

class StripePaymentMethodWarningService
{
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
                    'message' => 'This card expires soon (' . $method->card->exp_month . '/' . $method->card->exp_year . ')',
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

    public function isPaymentMethodExpired(mixed $paymentMethod): bool
    {
        if (!isset($paymentMethod->card)) {
            return false;
        }

        $expiryDate = new DateTime("{$paymentMethod->card->exp_year}-{$paymentMethod->card->exp_month}-01");
        $expiryDate->modify('last day of this month');

        return $expiryDate < new DateTime();
    }

    public function isPaymentMethodExpiring(mixed $paymentMethod, int $monthsThreshold = 2): bool
    {
        if (!isset($paymentMethod->card)) {
            return false;
        }

        $expiryDate = new DateTime("{$paymentMethod->card->exp_year}-{$paymentMethod->card->exp_month}-01");
        $expiryDate->modify('last day of this month');

        $now = new DateTime();
        $threshold = (clone $now)->modify("+{$monthsThreshold} months");

        return $expiryDate <= $threshold && $expiryDate >= $now;
    }
}
