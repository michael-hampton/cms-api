<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use DateTime;
use Throwable;

final class ShopAccountDeliveryController extends Controller
{
    public function __construct(
        private readonly SubscriptionDeliveryService $deliveryService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function status(int $id): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->ownsSubscription($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        return $this->jsonResponse($this->deliveryService->getPauseStatus($id));
    }

    public function pause(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->ownsSubscription($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $pauseStart = $this->date($request->input('pause_start'));
        $pauseEnd = $this->date($request->input('pause_end'));

        if (!$pauseStart || !$pauseEnd) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Pause start and end dates are required.',
            ], 422);
        }

        try {
            return $this->jsonResponse($this->deliveryService->pauseDelivery(
                $id,
                $pauseStart,
                $pauseEnd,
                $this->optionalString($request->input('reason')),
            ));
        } catch (Throwable $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function resume(int $id): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$this->ownsSubscription($id, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        try {
            return $this->jsonResponse($this->deliveryService->resumeDelivery($id));
        } catch (Throwable $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function ownsSubscription(int $id, int $memberId): bool
    {
        $subscription = $this->subscriptionRepository->find($id);

        return $subscription && $subscription->member_id === $memberId;
    }

    private function date(mixed $value): ?DateTime
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
