<?php

namespace App\Controllers\Subscription;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Enums\Subscriptions\PolicySettingKey;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\SubscriptionPolicySettingOverride;
use App\Repositories\Subscriptions\ReplacementPolicyRepository;
use App\Repositories\Subscriptions\SubscriptionPolicySettingOverrideRepository;
use App\Requests\Subscription\SubscriptionPolicySettingOverrideClearRequest;
use App\Requests\Subscription\SubscriptionPolicySettingOverrideRequest;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;
use App\Services\Subscriptions\PolicySettingOverrideResolver;
use App\Services\Subscriptions\SubscriptionPolicySettingOverrideService;
use InvalidArgumentException;

/**
 * Admin surface for "business decisions in subscriptions can be
 * overridden" — lets a site admin override individual pause/cancellation
 * policy settings (see PolicySettingKey) per policy class, rather than
 * an agent overriding a single request. Gated by the
 * `subscription_policies.override` permission, deliberately separate
 * from the `crm.subscriptions.*` permissions regular CRM agents hold, per
 * the ticket's "admin-only" requirement.
 */
class SubscriptionPolicyOverrideController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly SubscriptionPolicySettingOverrideService $overrideService,
        private readonly PolicySettingOverrideResolver $overrideResolver,
        private readonly ReplacementPolicyRepository $policyRepository,
        private readonly SubscriptionPolicySettingOverrideRepository $overrideRepository,
    ) {
        parent::__construct();
    }

    /**
     * GET /api/{site}/crm/subscription-policies/overrides
     *
     * Every policy configured for the site, its overridable settings,
     * their defaults, and any currently active override values — what
     * the admin UI's settings table renders directly.
     */
    public function index(): JsonResponse
    {
        if ($response = $this->requireSitePermission('subscription_policies.override')) {
            return $response;
        }

        $siteId = (int) SiteContext::getId();
        $policies = $this->policyRepository->listForSite($siteId);

        $result = [];
        foreach ($policies as $policy) {
            $policyClass = (string) $policy->policy_class;

            if ($policyClass === '' || !is_a($policyClass, ReplacementPolicyInterface::class, true)) {
                continue;
            }

            $defaults = $policyClass::overridableSettings();

            if (empty($defaults)) {
                continue; // e.g. GoodwillPolicy — nothing admin-overridable
            }

            $overrides = $this->overrideResolver->resolveForSitePolicy($siteId, $policyClass);

            $settings = [];
            foreach ($defaults as $key => $default) {
                $settingKey = PolicySettingKey::from($key);
                $settings[] = [
                    'key' => $key,
                    'label' => $settingKey->label(),
                    'value_type' => $settingKey->valueType(),
                    'default' => $default,
                    'overridden' => $overrides->has($settingKey),
                    'effective_value' => $overrides->get($settingKey, $default),
                ];
            }

            $result[] = [
                'policy_id' => $policy->id,
                'policy_class' => $policyClass,
                'policy_name' => $policy->name,
                'settings' => $settings,
            ];
        }

        return $this->resourceResponse(['policies' => $result]);
    }

    /**
     * GET /api/{site}/crm/subscription-policies/{policyClass}/overrides/history
     *
     * Full audit trail (active + cleared) for one policy class, newest
     * first.
     */
    public function history(string $policyClass): JsonResponse
    {
        if ($response = $this->requireSitePermission('subscription_policies.override')) {
            return $response;
        }

        $siteId = (int) SiteContext::getId();
        $history = $this->overrideRepository->historyForSitePolicy($siteId, urldecode($policyClass));

        return $this->resourceResponse([
            'history' => array_map(fn (SubscriptionPolicySettingOverride $override) => $this->format($override), $history->all()),
        ]);
    }

    /**
     * POST /api/{site}/crm/subscription-policies/overrides
     */
    public function store(SubscriptionPolicySettingOverrideRequest $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('subscription_policies.override')) {
            return $response;
        }

        try {
            $data = $request->validated();
            $settingKey = PolicySettingKey::tryFrom((string) $data['setting_key']);

            if ($settingKey === null) {
                return $this->errorResponse("\"{$data['setting_key']}\" is not a recognised policy setting.", 422);
            }

            $override = $this->overrideService->setOverride(
                siteId: (int) SiteContext::getId(),
                policyClass: (string) $data['policy_class'],
                settingKey: $settingKey,
                value: $data['value'],
                reason: (string) $data['reason'],
                adminUserId: (int) Auth::id(),
            );

            return $this->resourceResponse($this->format($override), 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422, $exception->getErrors());
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/crm/subscription-policies/overrides/clear
     */
    public function clear(SubscriptionPolicySettingOverrideClearRequest $request): JsonResponse
    {
        if ($response = $this->requireSitePermission('subscription_policies.override')) {
            return $response;
        }

        $data = $request->validated();
        $policyClass = (string) $data['policy_class'];
        $settingKey = PolicySettingKey::tryFrom((string) $data['setting_key']);

        if ($settingKey === null) {
            return $this->errorResponse('A valid setting_key is required.', 422);
        }

        try {
            $this->overrideService->clearOverride(
                siteId: (int) SiteContext::getId(),
                policyClass: $policyClass,
                settingKey: $settingKey,
                reason: (string) $data['reason'],
                adminUserId: (int) Auth::id(),
            );

            return $this->resourceResponse(['cleared' => true]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    // -------------------------------------------------------------------------

    private function format(SubscriptionPolicySettingOverride $override): array
    {
        return [
            'id' => $override->id,
            'site_id' => $override->site_id,
            'policy_class' => $override->policy_class,
            'setting_key' => $override->setting_key,
            'value' => $override->value,
            'reason' => $override->reason,
            'created_by_user_id' => $override->created_by_user_id,
            'active' => $override->active,
            'created_at' => $override->created_at?->format('c'),
        ];
    }
}