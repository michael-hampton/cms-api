<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\AdHocFulfilmentFileRequested;
use App\Framework\Support\Logger;

/**
 * Audit trail for manually-triggered fulfilment file generation. The
 * AdHocFulfilmentRequest row is the durable record; this listener
 * additionally surfaces the action in the application log stream so it
 * shows up alongside other admin actions without a dedicated audit query,
 * matching LogSubscriptionPolicySettingOverrideListener's pattern.
 */
class LogAdHocFulfilmentRequestListener
{
    public function handle(AdHocFulfilmentFileRequested $event): void
    {
        Logger::info('Ad-hoc fulfilment file generation requested', [
            'ad_hoc_fulfilment_request_id' => $event->request->id,
            'process' => $event->request->process,
            'print_batch_id' => $event->request->print_batch_id,
            'requested_by_user_id' => $event->request->requested_by_user_id,
        ]);
    }
}