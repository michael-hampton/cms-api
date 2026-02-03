<?php

namespace App\Services\Billing\Order;

use App\Repositories\Billing\OrderRepository;

class OrderNumberGenerator
{
    private const MAX_RETRIES = 5;
    private const PREFIX = 'ORD';

    public function __construct(
        private readonly OrderRepository $orderRepository
    )
    {
    }

    public function generate(): string
    {
        $attempts = 0;

        while ($attempts < self::MAX_RETRIES) {
            $orderNumber = $this->generateCandidate();

            if (!$this->orderRepository->findByOrderNumber($orderNumber)) {
                return $orderNumber;
            }

            $attempts++;
        }

        // Fallback: use UUID for guaranteed uniqueness
        return self::PREFIX . '-' . $this->generateUUID();
    }

    private function generateCandidate(): string
    {
        return sprintf(
            '%s-%d-%04d',
            self::PREFIX,
            time(),
            random_int(1000, 9999)
        );
    }

    private function generateUUID(): string
    {
        return bin2hex(random_bytes(8));
    }
}