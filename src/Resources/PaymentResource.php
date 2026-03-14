<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'subscription_id' => $this->getAttribute('subscription_id'),
            'order_id' => $this->getAttribute('order_id'),
            'site_id' => $this->getAttribute('site_id'),
            'amount' => $this->getAttribute('amount'),
            'currency' => $this->getAttribute('currency'),
            'status' => $this->getAttribute('status'),
            'payment_method' => $this->getAttribute('payment_method'),
            'transaction_id' => $this->getAttribute('transaction_id'),
            'payment_intent_id' => $this->getAttribute('payment_intent_id'),
            'error_message' => $this->getAttribute('error_message'),
            'error_data' => $this->getAttribute('error_data'),
            'paid_at' => $this->getAttribute('paid_at')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}