<?php

namespace App\Services\Subscriptions;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Models\SingleContentAccess;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SingleContentAccessRepository;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use Exception;

class SingleContentAccessService
{
    public function __construct(
        private readonly SingleContentAccessRepository    $accessRepository,
        private readonly PaymentRepository                $paymentRepository,
        private readonly StripePaymentIntentGatewayInterface $paymentIntentGateway,
        private readonly StripeCustomerGatewayInterface   $customerGateway,
        private readonly Database                         $database,
        private readonly Logger $logger,
        private readonly MemberRepository $memberRepository,
    ) {}

    public function purchaseAccess(
        int    $memberId,
        int    $siteId,
        string $contentType,
        int    $contentId,
        float  $price,
        string $currency,
        int    $durationDays,
    ): array {
        return $this->database->transaction(function () use (
            $memberId, $siteId, $contentType, $contentId, $price, $currency, $durationDays
        ) {
            if ($this->accessRepository->hasActiveAccess($memberId, $contentType, $contentId, $siteId)) {
                throw new Exception('You already have active access to this content');
            }

            $member     = $this->memberRepository->find($memberId);
            $customerId = $this->customerGateway->getOrCreate($member);

            $dto = new CreatePaymentIntentDto(
                amountCents:      (int) round($price * 100),
                currency:         $currency,
                metadata:         [
                    'member_id'              => $memberId,
                    'site_id'                => $siteId,
                    'content_type'           => $contentType,
                    'content_id'             => $contentId,
                    'single_content_access'  => true,
                ],
                stripeCustomerId: $customerId,
            );

            $paymentResult = $this->paymentIntentGateway->createWithCustomer($dto);

            if (!$paymentResult->success) {
                throw new Exception($paymentResult->errorMessage ?? 'Payment failed');
            }

            $expiresAt = (new \DateTime())->modify("+{$durationDays} days");

            $access = $this->accessRepository->createAccess([
                'member_id'    => $memberId,
                'site_id'      => $siteId,
                'content_type' => $contentType,
                'content_id'   => $contentId,
                'price'        => $price,
                'currency'     => $currency,
                'expires_at'   => $expiresAt->format('Y-m-d H:i:s'),
                'duration_days'=> $durationDays,
                'is_active'    => false,
                'metadata'     => [
                    'duration_days'    => $durationDays,
                    'payment_intent_id'=> $paymentResult->paymentIntentId,
                ],
            ]);

            $this->logger->info('Single content access created', [
                'access_id'    => $access->id,
                'member_id'    => $memberId,
                'content_type' => $contentType,
                'content_id'   => $contentId,
            ]);

            return [
                'success'           => true,
                'access_id'         => $access->id,
                'payment_intent_id' => $paymentResult->paymentIntentId,
                'client_secret'     => $paymentResult->clientSecret,
                'access_token'      => $access->access_token,
                'expires_at'        => $expiresAt->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function completeAccessPurchase(string $paymentIntentId): array
    {
        return $this->database->transaction(function () use ($paymentIntentId) {
            $paymentResult = $this->paymentIntentGateway->retrieve($paymentIntentId);

            if (!$paymentResult->success || $paymentResult->status !== 'succeeded') {
                throw new Exception('Payment was not successful');
            }

            $access = $this->accessRepository->findByPaymentIntent($paymentIntentId);

            if (!$access) {
                throw new Exception('Access record not found');
            }

            $payment = $this->paymentRepository->create([
                'site_id'           => $access->site_id,
                'payment_method'    => 'stripe',
                'payment_provider'  => 'stripe',
                'payment_intent_id' => $paymentIntentId,
                'transaction_id'    => $paymentIntentId,
                'status'            => 'completed',
                'amount'            => $access->price,
                'currency'          => $access->currency,
                'paid_at'           => now_datetime()->format('Y-m-d H:i:s'),
                'metadata'          => [
                    'single_content_access_id' => $access->id,
                    'content_type'             => $access->content_type,
                    'content_id'               => $access->content_id,
                ],
            ]);

            $access->update(['is_active' => true, 'payment_id' => $payment->id]);

            $this->logger->info('Single content access activated', [
                'access_id'  => $access->id,
                'payment_id' => $payment->id,
            ]);

            return ['success' => true, 'access' => $access, 'payment' => $payment];
        });
    }

    public function checkAccess(int $memberId, string $contentType, int $contentId, ?int $siteId = null): array
    {
        $access = $this->accessRepository->getActiveAccess($memberId, $contentType, $contentId, $siteId);

        if (!$access) {
            return ['has_access' => false, 'reason' => 'no_access'];
        }

        if (!$access->isValid()) {
            return [
                'has_access' => false,
                'reason'     => 'expired',
                'expired_at' => $access->expires_at->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'has_access' => true,
            'access'     => $access,
            'expires_at' => $access->expires_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function getMemberActiveAccess(int $memberId, ?int $siteId = null): array
    {
        $accessList = $this->accessRepository->getMemberActiveAccess($memberId, $siteId);

        return $accessList->map(function ($access) {
            $content = $access->getContent();

            return [
                'id'            => $access->id,
                'content_type'  => $access->content_type,
                'content_id'    => $access->content_id,
                'content_title' => $content?->title ?? 'Unknown',
                'purchased_at'  => $access->purchased_at->format('Y-m-d H:i:s'),
                'expires_at'    => $access->expires_at?->format('Y-m-d H:i:s'),
                'is_valid'      => $access->isValid(),
                'access_token'  => $access->access_token,
            ];
        })->toArray();
    }

    public function getContentAccessDetails(string $contentType, int $contentId): array
    {
        $pricing = [
            SingleContentAccess::CONTENT_TYPE_PAGE       => ['price' => 4.99, 'duration_days' => 30,  'currency' => 'USD'],
            SingleContentAccess::CONTENT_TYPE_NEWSLETTER => ['price' => 2.99, 'duration_days' => 90,  'currency' => 'USD'],
            SingleContentAccess::CONTENT_TYPE_REPORT     => ['price' => 9.99, 'duration_days' => 365, 'currency' => 'USD'],
        ];

        return $pricing[$contentType] ?? ['price' => 4.99, 'duration_days' => 30, 'currency' => 'USD'];
    }
}