<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BackIssue;

use App\Actions\Stock\FulfilSubscriptionAction;
use App\Actions\Subscriptions\Print\CreatePrintFulfillmentAction;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Stock\StockException;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\SubscriptionIssueFulfilment;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Orchestrates a single-issue (back-issue) order: given a subscription and
 * the specific already-printed issue the customer chose, reserves stock and
 * creates the Fulfilment on that exact issue — classified as STANDARD or
 * BACK_ISSUE by BackIssueClassifier.
 *
 * For print subscriptions, this also creates the address-resolved
 * PrintFulfillment record via CreatePrintFulfillmentAction — the same
 * collaborator the normal print-run pipeline uses — so back-issue orders get
 * a real, vendor-ready name/address record rather than a bespoke one. That
 * PrintFulfillment is later picked up by BackIssueReplacementCopyDispatchService,
 * NOT by GenerateLabelRunsJob (see that service's docblock for why).
 *
 * Assumption: this service is called once payment for the order has already
 * been confirmed by the caller (e.g. an order/checkout service), the same
 * way FulfilSubscriptionAction::confirm() is called in Phase 3 of
 * OneTimeSubscriptionCheckoutService. There is no separate reserve/confirm
 * split here because a back-issue purchase has no pre-order phase — the
 * issue is already in stock and printed, so reserve+confirm happen together
 * in one transaction.
 *
 * This service does NOT:
 *   - Decide issue eligibility beyond stock (that is IssueAvailabilityPolicy,
 *     which the caller is expected to have already checked before charging
 *     the customer).
 *   - Contain the back-issue/standard classification logic (BackIssueClassifier).
 *   - Resolve delivery addresses (CreatePrintFulfillmentAction / PrintAddressResolver).
 *   - Format or dispatch anything to a vendor (BackIssueReplacementCopyDispatchService).
 */
class BackIssueOrderService
{
    public function __construct(
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionIssueFulfilmentRepository $fulfilmentRepository,
        private readonly BackIssueClassifier $classifier,
        private readonly FulfilSubscriptionAction $fulfilSubscriptionAction,
        private readonly CreatePrintFulfillmentAction $createPrintFulfillmentAction,
        private readonly Database $database,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @throws \InvalidArgumentException When the issue or subscription does not exist.
     * @throws StockException When there is insufficient stock for the requested quantity.
     */
    public function order(int $subscriptionId, int $issueDeliveryId, int $quantity = 1): SubscriptionIssueFulfilment
    {
        return $this->database->transaction(function () use ($subscriptionId, $issueDeliveryId, $quantity) {
            $issue = $this->issueDeliveryRepository->find($issueDeliveryId);

            if (!$issue) {
                throw new \InvalidArgumentException("IssueDelivery #{$issueDeliveryId} not found.");
            }

            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
            }

            // reserve()+confirm() must both run inside this transaction —
            // see class docblock for why there is no separate Phase 3 here.
            $reservationId = $this->fulfilSubscriptionAction->reserve($issue, $quantity);
            $this->fulfilSubscriptionAction->confirm($reservationId);

            $type = $this->classifier->classify($issue);

            $fulfilment = $this->fulfilmentRepository->createBackIssueFulfilment(
                $subscriptionId,
                (int) $issue->id,
                $type,
            );

            if ($subscription->delivery_type === SubscriptionType::PRINTED->value) {
                $this->createPrintFulfillmentAction->execute($subscription, $issue);
            }

            $this->logger->info('BackIssueOrderService: fulfilment created', [
                'subscription_id' => $subscriptionId,
                'issue_delivery_id' => $issue->id,
                'type' => $type->value,
                'fulfilment_id' => $fulfilment->id,
            ]);

            return $fulfilment;
        });
    }
}
