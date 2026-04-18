<?php

namespace App\Mail\Campaigns;

class SoftReengagementMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Still thinking about us, {$this->memberFirstName()}?")
            ->markdown('emails.campaigns.soft-reengagement', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'exploreUrl' => $this->siteUrl() . '/explore',
            ]);
    }
}