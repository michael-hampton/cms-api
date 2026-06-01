<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class SubscriptionResource extends JsonResource
{

    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'member_id' => $this->getAttribute('member_id'),
            'member_name' => $this->getAttribute('member')?->first_name . ' ' . $this->getAttribute('member')?->last_name,
            'member_email' => $this->getAttribute('member')?->email,
            'site_id' => $this->getAttribute('site_id'),
            'plan_id' => $this->getAttribute('plan_id'),
            'plan_name' => $this->getAttribute('plan')?->name,
            'status' => $this->getAttribute('status'),
            'renewal_count' => $this->getAttribute('renewal_count') ?? 0,
            'first_renewed_at' => $this->getAttribute('first_renewed_at')?->format('Y-m-d'),
            'last_renewed_at' => $this->getAttribute('last_renewed_at')?->format('Y-m-d'),
            'start_date' => $this->getAttribute('start_date')?->format('Y-m-d'),
            'end_date' => $this->getAttribute('end_date')?->format('Y-m-d'),
            'next_billing_date' => $this->getAttribute('next_billing_date')?->format('Y-m-d'),
            'last_payment_date' => $this->getAttribute('last_payment_date')?->format('Y-m-d'),
            'price' => $this->getAttribute('price'),
            'currency' => $this->getAttribute('currency'),
            'auto_renew' => $this->getAttribute('auto_renew'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d'),
        ];
    }
}
