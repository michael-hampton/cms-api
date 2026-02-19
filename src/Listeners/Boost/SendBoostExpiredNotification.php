<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostExpiredEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostExpiredMail;
use App\Models\Merchant;
use App\Repositories\Adverts\Boost\BoostStatRepository;

class SendBoostExpiredNotification
{
    public function __construct(
        private readonly MailManager         $mailer,
        private readonly BoostStatRepository $boostStatRepository,
    )
    {
    }

    public function handle(BoostExpiredEvent $event): void
    {
        $boost = $event->boost;
        $merchant = Merchant::find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost expired email — merchant or contact missing', [
                'boost_id' => $boost->id,
            ]);
            return;
        }

        $stat = $this->boostStatRepository->findByBoost($boost->id);

        try {
            $this->mailer->to($merchant->contact->email)->send(new BoostExpiredMail($boost, $merchant, $stat));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost expired email', ['boost_id' => $boost->id, 'error' => $e->getMessage()]);
        }
    }
}