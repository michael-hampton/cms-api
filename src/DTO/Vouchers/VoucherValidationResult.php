<?php

namespace App\DTO\Vouchers;

use App\Models\Voucher;

class VoucherValidationResult
{
    public function __construct(
        public readonly bool     $valid,
        public readonly string   $message,
        public readonly float    $discount,
        public readonly float    $eligibleSubtotal,
        public readonly array    $eligibleItems,
        public readonly bool     $isStackable,
        public readonly bool     $requiresOverrideDecision,
        public readonly ?Voucher $voucher,
        public readonly ?float   $finalPrice = null
    )
    {
    }

    public static function valid(
        Voucher $voucher,
        float $discount,
        float $finalPrice = 0,
        float $eligibleSubtotal = 0,
        array $eligibleItems = [],
        bool  $isStackable = true,
        bool  $requiresOverrideDecision = false
    ): self
    {
        return new self(
            valid: true,
            message: 'Voucher applied successfully',
            discount: $discount,
            eligibleSubtotal: $eligibleSubtotal,
            eligibleItems: $eligibleItems,
            isStackable: $isStackable,
            requiresOverrideDecision: $requiresOverrideDecision,
            voucher: $voucher,
            finalPrice: $finalPrice
        );
    }

    public static function invalid(string $message): self
    {
        return new self(
            valid: false,
            message: $message,
            discount: 0,
            eligibleSubtotal: 0,
            eligibleItems: [],
            isStackable: false,
            requiresOverrideDecision: false,
            voucher: null
        );
    }

    public function toArray(): array
    {
        $result = [
            'valid' => $this->valid,
            'message' => $this->message,
            'discount' => $this->discount,
            'eligible_subtotal' => $this->eligibleSubtotal,
            'eligible_items' => $this->eligibleItems,
            'is_stackable' => $this->isStackable,
            'requires_override_decision' => $this->requiresOverrideDecision,
            'final_price' => $this->finalPrice
        ];

        if ($this->voucher) {
            $result['voucher_id'] = $this->voucher->id;
            $result['voucher'] = $this->voucher->toArray();
        }

        return $result;
    }
}