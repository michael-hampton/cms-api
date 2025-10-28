<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'order_number' => $this->getAttribute('order_number'),
            'user_id' => $this->getAttribute('user_id'),
            'status' => $this->getAttribute('status'),
            'subtotal' => $this->getAttribute('subtotal'),
            'tax' => $this->getAttribute('tax'),
            'shipping' => $this->getAttribute('shipping', true),
            'discount' => $this->getAttribute('discount'),
            'total' => $this->getAttribute('total'),
            'customer_notes' => $this->getAttribute('customer_notes'),
            'shipping_address' => is_string($this->getAttribute('shipping_address')) ? json_decode($this->getAttribute('shipping_address'), true) : $this->getAttribute('shipping_address'),
            'billing_address' => is_string($this->getAttribute('billing_address')) ? json_decode($this->getAttribute('billing_address'), true) : $this->getAttribute('billing_address'),
            'payment_status' => $this->getAttribute('payment_status'),
            'items' => $this->getAttribute('items'),
            'customer_name' => $this->getAttribute('customer_name'),
            'customer_email' => $this->getAttribute('customer_email'),
            'customer_phone' => $this->getAttribute('customer_phone'),
        ];
    }
}