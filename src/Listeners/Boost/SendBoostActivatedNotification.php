<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostActivatedEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostActivatedMail;
use App\Models\Merchant;

class SendBoostActivatedNotification
{
    public function __construct(private readonly MailManager $mailer)
    {
    }

    public function handle(BoostActivatedEvent $event): void
    {
        $boost = $event->boost;
        $merchant = Merchant::find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost activated email — merchant or contact missing', [
                'boost_id' => $boost->id,
            ]);
            return;
        }

        try {
            $this->mailer->to($merchant->contact->email)->send(new BoostActivatedMail($boost, $merchant));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost activated email', ['boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}