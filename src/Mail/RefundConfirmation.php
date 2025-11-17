<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Order;
use App\Models\Refund;

class RefundConfirmation extends Mailable
{
    public function __construct(
        private readonly Refund $refund,
        private readonly Order  $order
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this->subject("Refund Processed - Order #{$this->order->order_number}")
            ->view('emails.refund-confirmation')
            ->with([
                'refund' => $this->refund,
                'order' => $this->order,
                'customer_name' => $this->order->user ? $this->order->user->first_name : 'Customer'
            ]);
    }
}