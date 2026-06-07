<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ArticlePurchasedEvent;
use App\Jobs\OpenCollab\SettleLedgerEntryJob;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\PaymentTermsService;

class RecordSaleToEarningsLedger
{
    public function __construct(
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly PaymentTermsService $paymentTermsService,
    ) {}

    public function handle(ArticlePurchasedEvent $event): void
    {
        if (!$event->contributorId) {
            return;
        }

        $entry = $this->ledgerRepository->recordSale(
            userId: $event->contributorId,
            articleId: $event->pageId,
            amount: $event->payment->amount,
            currency: $event->payment->currency ?? 'GBP',
            referenceId: (string) $event->payment->stripe_payment_intent_id,
        );

        $terms = $this->paymentTermsService->forSite(
            (int) $event->payment->site_id
        );

        dispatch(new SettleLedgerEntryJob((int) $entry->id))
            ->delay(now_datetime()->addDays($terms->payout_delay_days))
            ->onQueue('ledger');
    }
}