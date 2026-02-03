<?php

namespace App\Listeners\Orders;

use App\Events\Orders\OrderCreatedEvent;
use App\Services\Billing\Order\OrderEmailNotifier;

class SendOrderConfirmationListener
{
    public function __construct(
        private readonly OrderEmailNotifier $emailNotifier
    )
    {
    }

    public function handle(OrderCreatedEvent $event): void
    {
        $this->emailNotifier->sendConfirmation(
            $event->order,
            $event->customerEmail
        );
    }
}