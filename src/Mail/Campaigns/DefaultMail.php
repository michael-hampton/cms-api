<?php

namespace App\Mail\Campaigns;

use App\Framework\Mail\Mailable;

class DefaultMail extends BaseCampaignMail
{

    public function build(): Mailable
    {
        return $this
            ->to($this->member->email)
            ->subject("People are buying this — don't miss out, {$this->memberFirstName()}")
            ->template((int)$this->campaign->template, [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'shopUrl' => $this->siteUrl() . '/shop',
            ]);
    }
}