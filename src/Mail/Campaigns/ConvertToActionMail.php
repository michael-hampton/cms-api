<?php

namespace App\Mail\Campaigns;

class ConvertToActionMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("People are buying this — don't miss out, {$this->memberFirstName()}")
            ->markdown('emails.campaigns.convert-to-action', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'shopUrl' => $this->siteUrl() . '/shop',
            ]);
    }
}