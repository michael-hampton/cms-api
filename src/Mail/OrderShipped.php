<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Order;

class OrderShipped extends Mailable
{
    public function __construct(
        public Order  $order,
        public string $trackingNumber,
        public string $carrier
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('Your Order Has Shipped - Order #' . $this->order->order_number)
            ->markdown('emails.orders.shipped')
            ->with([
                'order' => $this->order,
                'trackingNumber' => $this->trackingNumber,
                'carrier' => $this->carrier,
            ]);
    }
}