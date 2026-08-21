<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\PrintOrderGenerated;
use App\Framework\Support\Logger;

/**
 * Audit trail for print order generation. The generated print order itself
 * is the durable record; this listener surfaces the action in the
 * application log stream so it shows up alongside other fulfilment
 * activity, matching LogAdHocFulfilmentRequestListener's pattern.
 *
 * The event's docblock also calls out supplier notification and admin
 * summary emails as future listener candidates — those need a decision on
 * which notification channel/template to use and are left for whoever owns
 * that workflow, rather than guessed at here.
 */
class PrintOrderGeneratedListener
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(PrintOrderGenerated $event): void
    {
        $this->logger->info('PrintOrderGeneratedListener: print order generated', [
            'issue_delivery_id' => $event->issueDelivery->id,
            'print_order_issue_delivery_id' => $event->result->issueDeliveryId,
            'total_subscriber_copies' => $event->result->totalSubscriberCopies(),
            'record_count' => count($event->result->records),
        ]);
    }
}
