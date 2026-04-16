<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\DTO\Consents\ConsentActionContext;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\Member;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Services\Members\Consents\ConsentCommandService;
use App\Services\Members\Consents\ConsentQueryService;

class MemberConsentApiController extends Controller
{
    public function __construct(
        private readonly ConsentCommandService $commandService,
        private readonly ConsentQueryService   $queryService,
        private readonly ConsentTypeRepository $consentTypeRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $member = Member::find(1);

        $consents = $this->queryService->getMemberConsents($member);

        $groupedConsents = [];

        foreach ($consents as $consent) {

            $type = $consent['consent_type'];

            // 🔒 enforce system rules
            $isLocked =
                $type['category'] === 'essential' ||
                !empty($type['required']);

            if ($isLocked) {
                $consent['is_granted'] = true;

                // optional but useful for audit/UI consistency
                $consent['granted_at'] = $consent['granted_at'] ?? now();
            }

            $consent['is_locked'] = $isLocked;

            $category = $type['category'];

            if (!isset($groupedConsents[$category])) {
                $groupedConsents[$category] = [];
            }

            $groupedConsents[$category][] = $consent;
        }

        return $this->resourceResponse([
            'consents' => $groupedConsents
        ]);
    }

    public function getConsentTypes()
    {
        $types = $this->consentTypeRepository->findAllActive();

        return $this->resourceResponse(['data' => $types]);
    }

    /**
     * Update consent preferences
     */
    public function update(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $consents = $request->input('consents', []);
        $context = ConsentActionContext::fromRequest($request, 'web');

        try {
            $results = [];

            foreach ($consents as $consentCode => $granted) {
                try {
                    if ($granted) {
                        $results[$consentCode] = $this->commandService->grantConsent(
                            $member,
                            $consentCode,
                            $context
                        );
                    } else {
                        $results[$consentCode] = $this->commandService->revokeConsent(
                            $member,
                            $consentCode,
                            $context
                        );
                    }
                } catch (\Exception $e) {
                    $results[$consentCode] = ['error' => $e->getMessage()];
                }
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Consent preferences updated successfully',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update consent preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    public function auditHistory(Request $request): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $consentCode = $request->input('consent_code'); // optional filter

        $auditTrail = $this->queryService->getAuditTrail($member, $consentCode);

        $serialized = array_map(function (array $entry) {
            return [
                'action' => $entry['action'],
                'source' => $entry['source'],
                'ip_address' => $entry['ip_address'] ?? null,
                'reason' => $entry['reason'] ?? null,
                'previous_state' => $entry['previous_state'] ?? null,
                'new_state' => $entry['new_state'] ?? null,
                'created_at' => $entry['created_at']?->format('Y-m-d H:i:s'),
                'consent_type' => [
                    'code' => $entry['consentType']['code'],
                    'name' => $entry['consentType']['name'],
                    'category' => $entry['consentType']['category'],
                ],
                'admin_email' => $entry['adminUser']['email'] ?? null,
            ];
        }, $auditTrail);

        return $this->resourceResponse([
            'success' => true,
            'audit_trail' => $serialized,
        ]);
    }
}