<?php

namespace App\Services\Members;

class OrderCalculationService
{
    public function calculateOrderTotals(array $items, array $orderData = [], bool $applyDefaultTax = false): array
    {
        $subtotal = $orderData['subtotal'] ?? 0;
        $itemTaxTotal = 0;

        if (!empty($items)) {
            $subtotal = 0;

            // Calculate item-level totals
            foreach ($items as $item) {
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $itemTax = $item['tax'] ?? 0;

                $subtotal += $itemSubtotal;
                $itemTaxTotal += $itemTax;
            }
        }


        $shipping = $orderData['shipping'] ?? 0;
        $discount = $orderData['discount'] ?? 0;

        // Calculate order-level tax if not provided at item level
        $orderTax = $orderData['tax'] ?? 0;

        // Use item-level tax if available, otherwise use order-level tax
        $totalTax = $itemTaxTotal > 0 ? $itemTaxTotal : $orderTax;

        // If no tax at all, calculate from taxable amount (10%)
        if ($applyDefaultTax && $totalTax === 0) {
            $taxableAmount = $subtotal - $discount + $shipping;
            $totalTax = $taxableAmount * 0.1;
        }

        $total = $subtotal + $totalTax + $shipping - $discount;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($totalTax, 2),
            'shipping' => round($shipping, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2)
        ];
    }

    public function calculateItemTotal(array $item): array
    {
        $quantity = $item['quantity'] ?? 1;
        $unitPrice = $item['unit_price'] ?? 0;

        $subtotal = $unitPrice * $quantity;
        $tax = $item['tax'] ?? 0;
        $total = $subtotal + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2)
        ];
    }
}