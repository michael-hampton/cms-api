<?php

namespace App\Mail\Campaigns;

class RecommendedProductsMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Picked just for you, {$this->memberFirstName()} 🛍️")
            ->markdown('emails.campaigns.recommended-products', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'shopUrl' => $this->siteUrl() . '/shop',
            ]);
    }
}