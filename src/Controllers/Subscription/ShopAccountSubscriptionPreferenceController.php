<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\MemberSubscriptionService;

final class ShopAccountSubscriptionPreferenceController extends Controller
{
    public function __construct(
        private readonly MemberSubscriptionService $subscriptionService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function show(int $id): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $summary = $this->subscriptionService->getSubscriptionSummary(
            $member->id,
            $subscription->site_id,
        );

        return $this->jsonResponse([
            'success' => true,
            'preferences' => [
                'is_active' => $summary['is_active'],
                'email_notifications' => $summary['email_notifications'],
                'newsletter_frequency' => $summary['frequency'],
                'content_types' => $summary['content_types'],
                'category_preferences' => $summary['category_preferences'],
            ],
        ]);
    }

    public function update(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $data = [];
        foreach (['email_notifications', 'is_active'] as $key) {
            if ($request->has($key)) {
                $value = $this->boolean($request->input($key));
                if ($value === null) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => "{$key} must be boolean.",
                    ], 422);
                }
                $data[$key] = $value;
            }
        }

        if ($request->has('newsletter_frequency')) {
            $frequency = $request->input('newsletter_frequency');
            if (!is_string($frequency) || !in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Newsletter frequency is invalid.',
                ], 422);
            }
            $data['newsletter_frequency'] = $frequency;
        }

        $preference = $this->subscriptionService->updatePreferences(
            $member->id,
            $data,
            $subscription->site_id,
        );

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Email preferences updated.',
            'preferences' => [
                'is_active' => $preference->is_active,
                'email_notifications' => $preference->email_notifications,
                'newsletter_frequency' => $preference->newsletter_frequency,
            ],
        ]);
    }

    private function boolean(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }
}
