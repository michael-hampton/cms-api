<?php

namespace App\Listeners\Refunds;

use App\Events\Refunds\RefundCreated;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\RefundConfirmation;
use App\Repositories\Billing\OrderRepository;

class SendRefundNotification
{
    public function __construct(
        private readonly MailManager     $mailManager,
        private readonly OrderRepository $orderRepository,
        private readonly Logger          $logger
    )
    {
    }

    public function handle(RefundCreated $event): void
    {
        try {
            $order = $this->orderRepository->find($event->refund->order_id);

            if (!$order) {
                $this->logger->warning('Cannot send refund notification: order not found', [
                    'refund_id' => $event->refund->id,
                    'order_id' => $event->refund->order_id
                ]);
                return;
            }

            if (!$order->user || !$order->user->email) {
                $this->logger->info('Refund notification skipped: no customer email', [
                    'refund_id' => $event->refund->id,
                    'order_id' => $order->id
                ]);
                return;
            }

            $this->mailManager
                ->to($order->user->email)
                ->send(new RefundConfirmation($event->refund, $order));

        } catch (\Exception $e) {
            $this->logger->error('Failed to send refund notification', [
                'refund_id' => $event->refund->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}