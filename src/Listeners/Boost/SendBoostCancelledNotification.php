<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostCancelledEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostCancelledMail;
use App\Models\Merchant;

class SendBoostCancelledNotification
{
    public function __construct(private readonly MailManager $mailer)
    {
    }

    public function handle(BoostCancelledEvent $event): void
    {
        $boost = $event->boost;
        $merchant = Merchant::find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost cancelled email — merchant or contact missing', [
                'boost_id' => $boost->id,
            ]);
            return;
        }

        try {
            $this->mailer->to($merchant->contact->email)->send(new BoostCancelledMail($boost, $merchant));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost cancelled email', ['boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}