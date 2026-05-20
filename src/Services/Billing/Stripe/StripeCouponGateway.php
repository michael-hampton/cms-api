<?php

namespace App\Services\Billing\Stripe;

use App\Enums\Vouchers\VoucherType;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCouponGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly VoucherRepository $voucherRepository,
    ) {}

    /**
     * @return array{coupon_id: string, voucher_id: int, voucher_code: string}
     */
    public function getOrCreateForVoucher(int $voucherId, string $currency = 'gbp'): array
    {
        $voucher = $this->voucherRepository->find($voucherId);

        if (!$voucher instanceof Voucher) {
            throw new \RuntimeException("Voucher #{$voucherId} not found.");
        }

        $couponId = $this->resolveCouponId($voucher, strtolower($currency));

        return [
            'coupon_id' => $couponId,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
        ];
    }

    private function resolveCouponId(Voucher $voucher, string $currency): string
    {
        if (!empty($voucher->stripe_coupon_id)) {
            try {
                $this->stripe->coupons->retrieve($voucher->stripe_coupon_id);

                return $voucher->stripe_coupon_id;
            } catch (ApiErrorException) {
                // Recreate the coupon when the stored id is stale in Stripe.
            }
        }

        $coupon = $this->stripe->coupons->create($this->buildCouponPayload($voucher, $currency));

        $voucher->update(['stripe_coupon_id' => $coupon->id]);

        return $coupon->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCouponPayload(Voucher $voucher, string $currency): array
    {
        $payload = [
            'name' => $voucher->name ?: $voucher->code,
            'metadata' => [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
            ],
        ];

        if ($voucher->type === VoucherType::Percentage->value) {
            $payload['percent_off'] = (int) $voucher->value;
        } else {
            $payload['amount_off'] = (int) round($voucher->value * 100);
            $payload['currency'] = $currency;
        }

        if (!empty($voucher->duration_in_months)) {
            $payload['duration'] = 'repeating';
            $payload['duration_in_months'] = (int) $voucher->duration_in_months;
        } else {
            $payload['duration'] = 'once';
        }

        return $payload;
    }
}
