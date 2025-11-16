<?php

namespace App\Mail;

use App\Framework\Mail\Mailable;
use App\Models\Order;

class OrderConfirmation extends Mailable
{
    public function __construct(
        public Order $order
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('Order Confirmation - Order #' . $this->order->order_number)
            ->markdown('emails.orders.confirmation')
            ->with([
                'order' => $this->order,
                'customerName' => $this->getCustomerName(),
                'shippingAddress' => $this->getShippingAddress(),
                'billingAddress' => $this->getBillingAddress(),
            ]);
    }

    private function getCustomerName(): string
    {
        if ($this->order->user) {
            return $this->order->user->first_name . ' ' . $this->order->user->last_name;
        }

        return 'Valued Customer';
    }

    private function getShippingAddress(): ?array
    {
        if ($this->order->shipping_address_id && $this->order->shippingAddress) {
            return [
                'name' => $this->order->shippingAddress->name ?? '',
                'line1' => $this->order->shippingAddress->line1 ?? '',
                'line2' => $this->order->shippingAddress->line2 ?? '',
                'city' => $this->order->shippingAddress->city ?? '',
                'state' => $this->order->shippingAddress->state ?? '',
                'postal_code' => $this->order->shippingAddress->postal_code ?? '',
                'country' => $this->order->shippingAddress->country ?? '',
            ];
        }

        if ($this->order->shipping_address) {
            return $this->order->shipping_address;
        }

        return null;
    }

    private function getBillingAddress(): ?array
    {
        if ($this->order->billing_address_id && $this->order->billingAddress) {
            return [
                'name' => $this->order->billingAddress->name ?? '',
                'line1' => $this->order->billingAddress->line1 ?? '',
                'line2' => $this->order->billingAddress->line2 ?? '',
                'city' => $this->order->billingAddress->city ?? '',
                'state' => $this->order->billingAddress->state ?? '',
                'postal_code' => $this->order->billingAddress->postal_code ?? '',
                'country' => $this->order->billingAddress->country ?? '',
            ];
        }

        if ($this->order->billing_address) {
            return json_decode($this->order->billing_address, true);
        }

        return null;
    }
}