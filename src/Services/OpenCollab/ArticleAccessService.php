<?php

namespace App\Services\OpenCollab;

use App\Events\OpenCollab\ArticlePurchasedEvent;
use App\Exceptions\OpenCollab\DuplicatePurchaseException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\OpenCollab\Notifications\ArticlePaymentFailedNotification;
use App\Services\OpenCollab\Notifications\ArticlePaymentSucceededNotification;

/**
 * The single authority for reading and writing article access.
 *
 * Responsibilities:
 *   - canView()                — the only place access checks happen
 *   - grantAccessFromPayment() — called by the webhook handler after payment confirmed
 *
 * Ticket 3 change:
 *   grantAccessFromPayment() now performs a defensive eligibility check before
 *   creating financial records. This guards against stale PaymentIntents,
 *   disabled content, or manual Stripe/test edge cases that bypass the
 *   PaymentIntent creation guard in ArticlePaymentService.
 *
 *   If the page is no longer eligible the payment is still marked succeeded
 *   (the money moved — we cannot pretend it did not) but no earnings ledger
 *   entry is created. The event is logged clearly for finance review.
 */
class ArticleAccessService
{
    public function __construct(
        private readonly ArticleAccessRepository  $accessRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly EventDispatcher          $eventDispatcher,
        private readonly Database                 $database,
        private readonly Logger                   $logger,
        private readonly ActivityRepository       $activityRepository,
        private readonly NotificationDispatcher   $notificationDispatcher,
        private readonly PageRepository           $pageRepository,
    ) {}

    // ── canView ───────────────────────────────────────────────────────────────

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

    // ── grantAccessFromPayment ────────────────────────────────────────────────

    /**
     * Confirms a payment and grants access to the associated page.
     * Called exclusively by the Stripe webhook handler.
     *
     * Defensive eligibility check runs AFTER the payment is confirmed so
     * we never silently drop financial events. If ineligible, access is still
     * granted (the reader paid) but no earnings ledger entry is created and
     * the mismatch is logged for finance review.
     *
     * Duplicate webhook events are handled idempotently.
     *
     * @throws \RuntimeException if the payment record is not found (critical)
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
            $this->logger->info('Duplicate webhook received for already-succeeded payment.', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $page = $this->pageRepository->find($payment->page_id);

        // ── Defensive eligibility check (Ticket 3) ────────────────────────────
        // Run before any write so we can gate earnings creation independently
        // of access granting. The reader paid — they still get access.
        $eligibleForEarnings = $this->pageIsEligibleForEarnings($page, $payment);

        if (!$eligibleForEarnings) {
            $this->logIneligibleWebhookPayment($payment, $page);
        }

        try {
            $this->database->transaction(function () use ($payment): void {
                $this->paymentRepository->updateStatus($payment->id, 'succeeded');

                $this->accessRepository->create([
                    'site_id'    => $payment->site_id,
                    'page_id'    => $payment->page_id,
                    'user_id'    => $payment->user_id,
                    'email'      => $payment->email,
                    'granted_at' => date('Y-m-d H:i:s'),
                ]);
            });
        } catch (DuplicatePurchaseException $e) {
            $this->logger->info('Access already exists for this page/reader combination.', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            return;
        }

        $payment->refresh();

        $this->eventDispatcher->dispatch(new ArticlePurchasedEvent(
            payment:       $payment,
            pageId:        $payment->page_id,
            contributorId: (int) $page?->contributor_id,
            // Signal to the listener whether earnings should be created.
            // Listeners MUST check this flag before writing ledger entries.
            eligibleForEarnings: $eligibleForEarnings,
        ));

        if ($page) {
            $this->notificationDispatcher->dispatch(
                new ArticlePaymentSucceededNotification($payment, $page)
            );
        }

        if ($page?->contributor_id) {
            try {
                $this->activityRepository->record(
                    siteId:  (int) $payment->site_id,
                    userId:  (int) $page->contributor_id,
                    type:    \App\Enums\OpenCollab\ActivityEventType::PaymentReceived,
                    payload: [
                        'page_id'  => $payment->page_id,
                        'amount'   => $payment->amount,
                        'currency' => $payment->currency,
                    ],
                );
            } catch (\Throwable) {
                // Non-critical
            }
        }
    }

    // ── recordPaymentFailure ──────────────────────────────────────────────────

    /**
     * Marks a payment as failed. Called by the webhook on payment_intent.payment_failed.
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

        $page = $this->pageRepository->find($payment->page_id);
        if ($page) {
            $this->notificationDispatcher->dispatch(
                new ArticlePaymentFailedNotification($payment, $page)
            );
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * A page is eligible for earnings creation only when all four conditions
     * still hold at webhook time, the amount matches, and a contributor exists.
     *
     * This is intentionally stricter than isSellable() — we also verify the
     * PaymentIntent amount matches the current approved price to catch stale
     * intents created before an admin price change.
     */
    private function pageIsEligibleForEarnings(?Page $page, $payment): bool
    {
        if (!$page) {
            return false;
        }

        if (!$page->isSellable()) {
            return false;
        }

        if (empty($page->contributor_id)) {
            return false;
        }

        // Amount integrity check — PaymentIntent amount must match current approved price.
        // Catches stale intents created before an admin price update.
        if ((int) $payment->amount !== (int) $page->price) {
            return false;
        }

        return true;
    }

    /**
     * Log ineligible webhook payments clearly so finance can review.
     * Never silently drop these — finance gremlins love silence.
     */
    private function logIneligibleWebhookPayment($payment, ?Page $page): void
    {
        $this->logger->warning('Webhook payment received for ineligible page — earnings NOT created.', [
            'payment_intent_id' => $payment->stripe_payment_intent_id ?? null,
            'payment_id'        => $payment->id,
            'page_id'           => $payment->page_id,
            'page_visibility'   => $page?->visibility,
            'page_price'        => $page?->price,
            'payment_amount'    => $payment->amount,
            'premium_approved'  => $page?->premium_approved_at,
            'monetisation_disabled' => $page?->monetisation_disabled_at,
            'contributor_id'    => $page?->contributor_id,
            'reason'            => $this->ineligibilityReason($page, $payment),
        ]);
    }

    private function ineligibilityReason(?Page $page, $payment): string
    {
        if (!$page) {
            return 'Page not found.';
        }
        if ($page->visibility !== 'premium') {
            return 'Page is not premium content.';
        }
        if ($page->premium_approved_at === null) {
            return 'Page has not been approved for premium monetisation.';
        }
        if ($page->monetisation_disabled_at !== null) {
            return 'Monetisation has been disabled.';
        }
        if (empty($page->contributor_id)) {
            return 'Page has no contributor.';
        }
        if ((int) $payment->amount !== (int) $page->price) {
            return "PaymentIntent amount [{$payment->amount}] does not match approved price [{$page->price}].";
        }
        return 'Unknown.';
    }
}