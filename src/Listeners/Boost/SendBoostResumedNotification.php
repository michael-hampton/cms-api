<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostResumedEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostResumedMail;
use App\Models\Merchant;

class SendBoostResumedNotification
{
    public function __construct(private readonly MailManager $mailer)
    {
    }

    public function handle(BoostResumedEvent $event): void
    {
        $boost = $event->boost;
        $merchant = Merchant::find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost resumed email — merchant or contact missing', [
                'boost_id' => $boost->id,
            ]);
            return;
        }

        try {
            $this->mailer->to($merchant->contact->email)->send(new BoostResumedMail($boost, $merchant));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost resumed email', ['boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}