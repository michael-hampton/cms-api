<?php

namespace App\Services\OpenCollab;

use App\Events\OpenCollab\ArticlePurchasedEvent;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Repositories\OpenCollab\ArticlePaymentRepository;

/**
 * The single authority for reading and writing article access.
 *
 * Responsibilities:
 *   - canView()       — the only place access checks happen
 *   - grantAccess()   — called by the webhook handler after payment confirmed
 *
 * Access is NEVER granted by the payment service or the webhook controller directly.
 * They delegate here. This enforces a clean seam for future access sources
 * (e.g. subscription-based access) without touching payment code.
 */
class ArticleAccessService
{
    public function __construct(
        private readonly ArticleAccessRepository  $accessRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly EventDispatcher          $eventDispatcher,
        private readonly Database                 $database,
        private readonly Logger                   $logger,
    )
    {
    }

    /**
     * Returns true if this reader may view the given page.
     *
     * Free pages: always accessible.
     * Paid pages: requires a record in oc_article_access.
     */
    public function canView(Page $page, ?int $userId, ?string $email): bool
    {
        if (!$page->is_paid) {
            return true;
        }

        if ($userId !== null && $this->accessRepository->hasAccessByUserId($page->id, $userId)) {
            return true;
        }

        if ($email !== null && $this->accessRepository->hasAccessByEmail($page->id, $email)) {
            return true;
        }

        return false;
    }

    /**
     * Confirms a payment and grants access to the associated page.
     * Called exclusively by the Stripe webhook handler.
     *
     * Non-duplicate-purchase path is the happy path.
     * Duplicate is caught and logged, not rethrown — webhooks can fire more than once.
     *
     * @throws \RuntimeException if the payment record is not found (critical — rethrow)
     */
    public function grantAccessFromPayment(string $paymentIntentId): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            throw new \RuntimeException(
                "Cannot grant access: payment intent [{$paymentIntentId}] not found."
            );
        }

        if ($payment->hasSucceeded()) {
            // Idempotent — webhook was retried, access was already granted.
            $this->logger->info('Duplicate webhook received for already-succeeded payment.', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        try {
            $this->database->transaction(function () use ($payment): void {
                $this->paymentRepository->updateStatus($payment->id, 'succeeded');

                $this->accessRepository->create([
                    'site_id' => $payment->site_id,
                    'page_id' => $payment->page_id,
                    'user_id' => $payment->user_id,
                    'email' => $payment->email,
                    'granted_at' => date('Y-m-d H:i:s'),
                ]);
            });
        } catch (DuplicatePurchaseException $e) {
            // Access record already exists — idempotent, log and move on.
            $this->logger->info('Access already exists for this page/reader combination.', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        // Fetch the updated payment for the event payload.
        $payment->refresh();

        $page = \App\Models\Page::find($payment->page_id);

        $this->eventDispatcher->dispatch(new ArticlePurchasedEvent(
            payment: $payment,
            pageId: $payment->page_id,
            contributorId: (int)$page?->contributor_id,
        ));
    }

    /**
     * Marks a payment as failed. Called by the webhook handler on payment_intent.payment_failed.
     * Non-critical: log and continue if the record is missing (e.g. race condition).
     */
    public function recordPaymentFailure(string $paymentIntentId): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            $this->logger->warning('Received failure webhook for unknown payment intent.', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $this->paymentRepository->updateStatus($payment->id, 'failed');
    }
}