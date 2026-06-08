<?php

namespace App\Services\OpenCollab;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\Enums\OpenCollab\PaymentStatus;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Database\Database;
use App\Models\Page;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use App\Services\Cms\Pages\PremiumPagePurchaseEligibilityService;

/**
 * Handles the payment initiation half of the purchase flow.
 *
 * Responsibilities:
 *   - Guard against duplicate purchases
 *   - Create a Stripe PaymentIntent
 *   - Persist a pending ArticlePayment record
 *
 * This service does NOT grant access. Access is granted by ArticleAccessService
 * after Stripe confirms success via webhook.
 *
 * Two writes (Stripe call + DB insert) are wrapped in a transaction so a
 * failed DB insert does not leave a dangling PaymentIntent silently.
 */
class ArticlePaymentService
{
    public function __construct(
        private readonly ArticlePaymentRepository   $paymentRepository,
        private readonly ArticleAccessRepository    $accessRepository,
        private readonly Database                   $database,
        private readonly StripePaymentIntentGateway $paymentIntentGateway,
        private readonly PremiumPagePurchaseEligibilityService $purchaseEligibilityService,
    )
    {
    }

    /**
     * Initiates a purchase for a paid page.
     *
     * Returns the pending ArticlePayment with stripe_payment_intent_id and
     * a client_secret the frontend uses to confirm the payment.
     *
     * @throws DuplicatePurchaseException if this reader already has access
     * @throws \InvalidArgumentException  if the page is not a paid page
     */
    public function initiatePayment(Page $page, ?int $userId, string $email): array
    {
        $this->purchaseEligibilityService->assertPurchasable($page);

        $this->guardAgainstDuplicatePurchase($page->id, $userId, $email);

        return $this->database->transaction(function () use ($page, $userId, $email): array {
            $intent = $this->paymentIntentGateway->create(
                new CreatePaymentIntentDto(
                    $page->price * 100,
                    'gbp',
                    [
                        'page_id' => $page->id,
                        'email' => $email,
                        'user_id' => $userId ?? 'guest',
                    ]
                )
            );

            $payment = $this->paymentRepository->create([
                'site_id' => $page->site_id,
                'page_id' => $page->id,
                'user_id' => $userId,
                'email' => $email,
                'stripe_payment_intent_id' => $intent->paymentIntentId,
                'status' => PaymentStatus::Pending->value,
                'amount' => $page->price,
                'currency' => 'gbp',
            ]);

            return [
                'payment' => $payment,
                'client_secret' => $intent->clientSecret,
            ];
        });
    }

    private function guardAgainstDuplicatePurchase(int $pageId, ?int $userId, string $email): void
    {
        $hasAccess = $userId !== null
            ? $this->accessRepository->hasAccessByUserId($pageId, $userId)
            : $this->accessRepository->hasAccessByEmail($pageId, $email);

        if ($hasAccess) {
            throw new DuplicatePurchaseException();
        }
    }
}