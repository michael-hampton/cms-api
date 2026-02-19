<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostPausedEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostPausedMail;
use App\Models\Merchant;

class SendBoostPausedNotification
{
    public function __construct(private readonly MailManager $mailer)
    {
    }

    public function handle(BoostPausedEvent $event): void
    {
        $boost = $event->boost;
        $merchant = Merchant::find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost paused email — merchant or contact missing', [
                'boost_id' => $boost->id,
            ]);
            return;
        }

        try {
            $this->mailer->to($merchant->contact->email)->send(new BoostPausedMail($boost, $merchant));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost paused email', ['boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}