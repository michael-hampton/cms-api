<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;

class SubscriptionCommunicationHistoryController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationDeliveryRepository $deliveryRepository,
    ) {
        parent::__construct();
    }

    public function index(int $subscriptionId): JsonResponse
    {
        $deliveries = $this->deliveryRepository->getForSubscription($subscriptionId);

        $history = $deliveries->map(fn($delivery) => [
            'id'            => $delivery->id,
            'communication' => $delivery->communication?->name,
            'type'          => $this->enumValue($delivery->communication?->type),
            'channel'       => $delivery->channel,
            'status'        => $this->enumValue($delivery->status),
            'sent_at'       => $delivery->sent_at?->format(DATE_ATOM),
            'opened_at'     => $delivery->opened_at?->format(DATE_ATOM),
            'clicked_at'    => $delivery->clicked_at?->format(DATE_ATOM),
        ])->toArray();

        return $this->jsonResponse(['history' => $history]);
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
