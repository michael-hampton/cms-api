<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\RedirectResponse;
use App\Framework\Http\Response;
use App\Framework\Support\Logger;
use App\Models\SubscriptionCommunicationEvent;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;

class SubscriptionCommunicationTrackingController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationDeliveryRepository $deliveryRepository,
    ) {
        parent::__construct();
    }

    public function open(string $token): Response
    {
        $delivery = $this->deliveryRepository->findByToken($token);

        if ($delivery === null) {
            return $this->successResponse('good');
        }

        try {
            SubscriptionCommunicationEvent::create([
                'subscription_communication_delivery_id' => $delivery->id,
                'event_type' => 'opened',
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ]);

            $this->deliveryRepository->markOpenedAt($delivery->id);
        } catch (\Throwable $e) {
            Logger::warning('SubscriptionCommunicationTrackingController: open tracking failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }

        // Return 1×1 transparent GIF
        return response(
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            200,
            ['Content-Type' => 'image/gif']
        );
    }

    public function click(string $token): RedirectResponse
    {
        $delivery = $this->deliveryRepository->findByToken($token);

        if ($delivery === null) {
            return $this->redirect('/');
        }

        $url = request()->query('url', '/');

        try {
            SubscriptionCommunicationEvent::create([
                'subscription_communication_delivery_id' => $delivery->id,
                'event_type' => 'clicked',
                'url'        => $url,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ]);

            $this->deliveryRepository->markClickedAt($delivery->id);
        } catch (\Throwable $e) {
            Logger::warning('SubscriptionCommunicationTrackingController: click tracking failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->redirect($url);
    }
}