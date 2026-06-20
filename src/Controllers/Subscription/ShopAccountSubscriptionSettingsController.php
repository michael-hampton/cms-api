<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Services\Subscriptions\MemberSubscriptionService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ShopAccountSubscriptionSettingsController extends Controller
{
    public function __construct(
        private readonly MemberSubscriptionService $subscriptionService,
    ) {
        parent::__construct();
    }

    public function updateAutoRenew(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorised.',
            ], 401);
        }

        if (!$request->has('auto_renew')) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Auto-renew must be provided.',
            ], 422);
        }

        $autoRenew = $this->strictBoolean($request->input('auto_renew'));

        if ($autoRenew === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Auto-renew must be a boolean value.',
            ], 422);
        }

        $consentGiven = false;

        if ($request->has('consent_given')) {
            $consentGiven = $this->strictBoolean($request->input('consent_given'));

            if ($consentGiven === null) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Consent must be a boolean value.',
                ], 422);
            }
        }

        try {
            $result = $this->subscriptionService->updateAutoRenew(
                $id,
                $member->id,
                $autoRenew,
                $consentGiven,
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => $autoRenew
                    ? 'Auto-renewal enabled.'
                    : 'Auto-renewal disabled.',
                'auto_renew' => $result['auto_renew'],
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        } catch (RuntimeException $exception) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update auto-renewal.',
            ], 500);
        }
    }

    private function strictBoolean(mixed $value): ?bool
    {
        return match (true) {
            $value === true,
            $value === 1,
            $value === '1' => true,
            $value === false,
            $value === 0,
            $value === '0' => false,
            default => null,
        };
    }
}
