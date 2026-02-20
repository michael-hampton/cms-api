<?php

namespace App\Mail\Subscriptions;

use App\Framework\Mail\Mailable;
use App\Models\Member;
use App\Models\Subscription;

/**
 * Confirmation email sent to a member after they successfully link
 * their print subscription to their online account.
 */
class SubscriptionLinkedMail extends Mailable
{
    public function __construct(
        private readonly Member       $member,
        private readonly Subscription $subscription,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $firstName = $this->member->first_name ?? 'there';
        $planName = $this->subscription->plan_name ?? 'your subscription';

        return $this
            ->subject('Your subscription is now linked')
            ->markdown('emails.subscriptions.linked', [
                'firstName' => $firstName,
                'planName' => $planName,
                'manageUrl' => url('/member/subscriptions'),
            ]);
    }
}