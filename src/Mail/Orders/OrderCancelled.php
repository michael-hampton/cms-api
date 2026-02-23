<?php

namespace App\Mail\Orders;

use App\Framework\Mail\Mailable;
use App\Models\Order;

class OrderCancelled extends Mailable
{
    public function __construct(
        public Order   $order,
        public ?string $reason = null
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('Order Cancelled - Order #' . $this->order->order_number)
            ->markdown('emails.orders.cancelled')
            ->with([
                'order' => $this->order,
                'reason' => $this->reason,
            ]);
    }
}