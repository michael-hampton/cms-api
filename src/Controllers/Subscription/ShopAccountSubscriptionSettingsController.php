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

        $autoRenew = $request->boolean('auto_renew');

        var_dump($autoRenew);
        die;

        $consentGiven = $request->boolean('consent_given');

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
}
