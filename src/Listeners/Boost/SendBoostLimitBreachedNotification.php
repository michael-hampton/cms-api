<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostLimitBreachedEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostLimitBreachedMail;
use App\Models\Merchant;

class SendBoostLimitBreachedNotification
{
    public function __construct(private readonly MailManager $mailer)
    {
    }

    public function handle(BoostLimitBreachedEvent $event): void
    {
        $boost = $event->boost;
        $merchant = Merchant::find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost limit breached email — merchant or contact missing', [
                'boost_id' => $boost->id,
            ]);
            return;
        }

        try {
            $this->mailer->to($merchant->contact->email)->send(new BoostLimitBreachedMail(
                boost: $boost,
                merchant: $merchant,
                limitType: $event->limitType,
                limitValue: $event->limitValue,
                currentValue: $event->currentValue,
            ));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost limit breached email', ['boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}