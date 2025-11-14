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
            'payment_status' => $this->getAttribute('payment_status'),
            'items' => $this->getAttribute('items'),
            'customer_name' => $this->getAttribute('customer_name'),
            'customer_email' => $this->getAttribute('customer_email'),
            'customer_phone' => $this->getAttribute('customer_phone'),
            'history' => $this->getAttribute('history'),
            'created_at' => $this->getAttribute('created_at')->format('Y-m-d H:i:s'),
            'shipping_address' => $this->when(
                !empty($this->shippingAddress),
                AddressResource::make($this->shippingAddress)->toArray()
            ),
            'billing_address' => $this->when(
                !empty($this->billingAddress),
                AddressResource::make($this->billingAddress)->toArray()
            ),
        ];
    }
}