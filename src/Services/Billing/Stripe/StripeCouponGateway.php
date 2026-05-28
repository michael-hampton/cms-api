<?php

namespace App\Services\Billing\Stripe;

use App\Enums\Vouchers\VoucherType;
use App\Framework\Database\Database;
use App\Models\Voucher;
use App\Repositories\Vouchers\VoucherRepository;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\ErrorObject;
use Stripe\StripeClient;

class StripeCouponGateway
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly VoucherRepository $voucherRepository,
        private readonly Database $database,
    ) {}

    /**
     * @return array{coupon_id: string, voucher_id: int, voucher_code: string, duration: ?string, duration_in_months: ?int}
     */
    public function getOrCreateForVoucher(int $voucherId, string $currency = 'gbp'): array
    {
        $voucher = $this->voucherRepository->find($voucherId);

        if (!$voucher instanceof Voucher) {
            throw new \RuntimeException("Voucher #{$voucherId} not found.");
        }

        $this->assertVoucherSupportsSubscriptions($voucher);

        $couponId = $this->resolveCouponId($voucher->id, strtolower($currency));

        $voucher = $this->voucherRepository->find($voucherId) ?? $voucher;

        return [
            'coupon_id' => $couponId,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'duration' => $voucher->getSubscriptionDiscountDuration(),
            'duration_in_months' => $voucher->getSubscriptionDurationMonths(),
        ];
    }

    private function resolveCouponId(int $voucherId, string $currency): string
    {
        return $this->database->transaction(function () use ($voucherId, $currency) {
            $voucher = $this->voucherRepository->lockForUpdate($voucherId);

            if (!$voucher instanceof Voucher) {
                throw new \RuntimeException("Voucher #{$voucherId} not found.");
            }

            $this->assertVoucherSupportsSubscriptions($voucher);

            if (!empty($voucher->stripe_coupon_id)) {
                try {
                    $this->stripe->coupons->retrieve($voucher->stripe_coupon_id);

                    return $voucher->stripe_coupon_id;
                } catch (InvalidRequestException $e) {
                    if (!$this->isMissingCouponException($e)) {
                        throw $e;
                    }
                }
            }

            $coupon = $this->stripe->coupons->create($this->buildCouponPayload($voucher, $currency));

            $voucher->update([
                'stripe_coupon_id' => $coupon->id,
                'stripe_coupon_synced_at' => now(),
            ]);

            return $coupon->id;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCouponPayload(Voucher $voucher, string $currency): array
    {
        $duration = $voucher->getSubscriptionDiscountDuration() ?? 'once';
        $durationInMonths = $voucher->getSubscriptionDurationMonths();
        $discountType = $voucher->getStripeDiscountType();
        $amountOff = $voucher->getStripeAmountOff();
        $percentOff = $voucher->getStripePercentOff();

        if ($duration === 'repeating' && ($durationInMonths === null || $durationInMonths < 1)) {
            throw new \DomainException('Repeating subscription vouchers require subscription_duration_months.');
        }

        if ($duration !== 'repeating' && $durationInMonths !== null) {
            throw new \DomainException('Only repeating subscription vouchers can set subscription_duration_months.');
        }

        if ($discountType === VoucherType::Percentage->value && ($percentOff === null || $percentOff < 1 || $percentOff > 100)) {
            throw new \DomainException('Percentage subscription vouchers require a percentage between 1 and 100.');
        }

        if ($discountType === VoucherType::Fixed->value && ($amountOff === null || $amountOff < 1)) {
            throw new \DomainException('Fixed subscription vouchers require a positive discount amount.');
        }

        $payload = [
            'name' => $voucher->name ?: $voucher->code,
            'duration' => $duration,
            'metadata' => [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
            ],
        ];

        if ($discountType === VoucherType::Percentage->value) {
            $payload['percent_off'] = $percentOff;
        } else {
            $payload['amount_off'] = $amountOff;
            $payload['currency'] = $currency;
        }

        if ($duration === 'repeating') {
            $payload['duration_in_months'] = $durationInMonths;
        }

        return $payload;
    }

    private function assertVoucherSupportsSubscriptions(Voucher $voucher): void
    {
        if (!$voucher->appliesToSubscriptions()) {
            throw new \DomainException('This voucher cannot be used to create Stripe subscription coupons.');
        }
    }

    private function isMissingCouponException(InvalidRequestException $exception): bool
    {
        $error = $exception->getError();

        return $exception->getHttpStatus() === 404
            || ($error && ($error->code ?? null) === ErrorObject::CODE_RESOURCE_MISSING);
    }
}
