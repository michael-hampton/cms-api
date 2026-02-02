<?php

namespace App\DTO;

use App\Models\Voucher;

class VoucherValidationResult
{
    public function __construct(
        public readonly bool     $valid,
        public readonly ?string  $message = null,
        public readonly float    $discount = 0,
        public readonly ?int     $voucherId = null,
        public readonly ?Voucher $voucher = null,
        public readonly ?float   $finalPrice = null
    )
    {
    }

    public static function valid(
        Voucher $voucher,
        float   $discount,
        ?float  $finalPrice = null
    ): self
    {
        return new self(
            valid: true,
            message: 'Voucher applied successfully',
            discount: $discount,
            voucherId: $voucher->id,
            voucher: $voucher,
            finalPrice: $finalPrice
        );
    }

    public static function invalid(string $message): self
    {
        return new self(
            valid: false,
            message: $message
        );
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'message' => $this->message,
            'discount' => $this->discount,
            'voucher_id' => $this->voucherId,
            'voucher' => $this->voucher,
            'final_price' => $this->finalPrice
        ];
    }
}