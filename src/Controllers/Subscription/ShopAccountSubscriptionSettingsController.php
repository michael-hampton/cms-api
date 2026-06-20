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

        $autoRenew = $this->booleanInput($request, 'auto_renew');
        $consentGiven = $this->booleanInput($request, 'consent_given', false);

        if ($autoRenew === null || $consentGiven === null) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Auto-renew and consent values must be boolean.',
            ], 422);
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

    private function booleanInput(
        Request $request,
        string $key,
        ?bool $default = null,
    ): ?bool {
        if (!$request->has($key)) {
            return $default;
        }

        $value = $request->input($key);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return null;
    }
}
