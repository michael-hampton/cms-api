<?php

namespace App\Mail\Members;

use App\Framework\Mail\Mailable;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Order;

/**
 * Activation email sent to members created via guest checkout.
 *
 * This mail class is responsible only for assembling the message.
 * It does not generate tokens (that is the listener's responsibility).
 * It does not know how the link will be handled (that is the controller's
 * responsibility).
 */
class AccountActivationMail extends Mailable
{
    public function __construct(
        private readonly Member $member,
        private readonly string $plainTextToken,
        private readonly Order  $order
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        $site = SiteContext::get();

        return $this
            ->subject("Create your password for {$site->name}")
            ->markdown('emails.members.account-activation')
            ->with([
                'member' => $this->member,
                'activationUrl' => $this->buildActivationUrl(),
                'orderNumber' => $this->order->order_number,
                'siteName' => $site->name ?? 'Your Account',
                'expiryHours' => 48,
            ]);
    }

    private function buildActivationUrl(): string
    {
        $site = SiteContext::get();
        $baseUrl = rtrim($site->url ?? 'http://localhost', '/');
        $slug = SiteContext::slug();

        // Thread the order number through so the controller can redirect to
        // the order detail page after a successful activation.
        return "{$baseUrl}/{$slug}/account/activate/{$this->plainTextToken}"
            . "?order={$this->order->order_number}";
    }
}