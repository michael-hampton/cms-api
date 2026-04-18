<?php

namespace App\Mail\Campaigns;

use App\Framework\Mail\Mailable;
use App\Models\Campaign;
use App\Models\Member;

/**
 * Base class for all campaign mailables.
 *
 * Establishes the standard constructor signature (Member + Campaign)
 * that SendCampaignJob relies on when instantiating campaign mailables
 * via reflection: new $mailableClass($member, $campaign).
 *
 * Subclasses must implement build() and call ->to() themselves since
 * the member's email is always available via $this->member.
 */
abstract class BaseCampaignMail extends Mailable
{
    public function __construct(
        protected readonly Member   $member,
        protected readonly Campaign $campaign,
    )
    {
        parent::__construct();
    }

    protected function memberFirstName(): string
    {
        return $this->member->first_name ?? 'there';
    }

    protected function siteUrl(): string
    {
        return rtrim(config('app.url', 'http://localhost'), '/');
    }
}