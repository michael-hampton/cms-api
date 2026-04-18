<?php

namespace App\Mail\Campaigns;

class NudgeToCommentMail extends BaseCampaignMail
{
    public function build(): self
    {
        return $this
            ->to($this->member->email)
            ->subject("Your opinion matters — share your thoughts 💭")
            ->markdown('emails.campaigns.nudge-to-comment', [
                'firstName' => $this->memberFirstName(),
                'siteUrl' => $this->siteUrl(),
                'exploreUrl' => $this->siteUrl() . '/explore',
            ]);
    }
}