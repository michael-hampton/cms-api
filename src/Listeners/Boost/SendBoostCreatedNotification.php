<?php

namespace App\Listeners\Boost;

use App\Events\Boost\BoostCreatedEvent;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Mail\Boost\BoostCreatedMail;
use App\Repositories\Product\MerchantRepository;

class SendBoostCreatedNotification
{
    public function __construct(
        private readonly MailManager        $mailer,
        private readonly MerchantRepository $merchantRepository
    )
    {
    }

    public function handle(BoostCreatedEvent $event): void
    {
        $boost = $event->boost;
        $merchant = $this->merchantRepository->find($boost->merchant_id);

        if (!$merchant || !$merchant->contact) {
            Logger::warning('Cannot send boost created email — merchant or contact missing', [
                'boost_id' => $boost->id,
                'merchant_id' => $boost->merchant_id,
            ]);
            return;
        }

        try {
            $this->mailer
                ->to($merchant->contact->email)
                ->send(new BoostCreatedMail($boost, $merchant));
        } catch (\Exception $e) {
            Logger::error('Failed to send boost created email', [
                'boost_id' => $boost->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}