<?php

namespace App\Services\Billing\Refund;

class RefundAmountCalculator
{
    public function calculateFromItems(array $items): float
    {
        return array_reduce($items, function (float $sum, array $item): float {
            $this->validateItemStructure($item);
            return $sum + $this->calculateItemRefundAmount($item);
        }, 0.0);
    }

    private function validateItemStructure(array $item): void
    {
        if (!isset($item['product_name'])) {
            throw new \InvalidArgumentException('Item missing required field: product_name');
        }
    }

    private function calculateItemRefundAmount(array $item): float
    {
        // Prefer explicit refund_amount
        if (isset($item['refund_amount']) && $item['refund_amount'] > 0) {
            return (float)$item['refund_amount'];
        }

        // Fallback: calculate from quantity and unit price
        $refundQuantity = $item['refund_quantity'] ?? $item['quantity'] ?? 0;
        $unitPrice = $item['unit_price'] ?? 0;

        if ($refundQuantity > ($item['quantity'] ?? 0)) {
            throw new \InvalidArgumentException(
                "Refund quantity ({$refundQuantity}) cannot exceed order quantity ({$item['quantity']})"
            );
        }

        return (float)($refundQuantity * $unitPrice);
    }
}