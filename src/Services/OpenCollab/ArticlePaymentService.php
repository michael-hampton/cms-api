<?php

namespace App\Services\OpenCollab;

use App\DTO\Stripe\CreatePaymentIntentDto;
use App\Enums\OpenCollab\PaymentStatus;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
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
 * IMPORTANT: the Stripe call is made *before* and *outside* the DB transaction.
 * A DB transaction can only roll back local writes — it has no effect on the
 * external PaymentIntent that Stripe already created, so wrapping both in one
 * transaction cannot prevent a "dangling PaymentIntent". If Stripe succeeds and
 * the subsequent DB insert then fails, the PaymentIntent is real and orphaned
 * regardless of transaction boundaries. We log that case at CRITICAL/error
 * level with the PaymentIntent id so it can be reconciled or cancelled
 * out-of-band, and rethrow so the caller sees the failure.
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

        // Stripe call happens outside the DB transaction — see class docblock.
        $intent = $this->paymentIntentGateway->create(
            new CreatePaymentIntentDto(
                $page->price,
                'gbp',
                [
                    'page_id' => $page->id,
                    'email' => $email,
                    'user_id' => $userId ?? 'guest',
                ]
            )
        );

        try {
            $payment = $this->database->transaction(function () use ($page, $userId, $email, $intent) {
                return $this->paymentRepository->create([
                    'site_id' => $page->site_id,
                    'page_id' => $page->id,
                    'user_id' => $userId,
                    'email' => $email,
                    'stripe_payment_intent_id' => $intent->paymentIntentId,
                    'status' => PaymentStatus::Pending->value,
                    'amount' => $page->price,
                    'currency' => 'gbp',
                ]);
            });
        } catch (\Throwable $e) {
            // The PaymentIntent already exists on Stripe's side and cannot be
            // rolled back by this failed DB write. Log loudly so it can be
            // reconciled/cancelled out-of-band, then rethrow.
            Logger::error('Orphaned Stripe PaymentIntent: DB insert failed after PaymentIntent creation', [
                'stripe_payment_intent_id' => $intent->paymentIntentId,
                'page_id' => $page->id,
                'user_id' => $userId,
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        return [
            'payment' => $payment,
            'client_secret' => $intent->clientSecret,
        ];
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