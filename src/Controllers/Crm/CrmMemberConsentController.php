<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\DTO\Consents\ConsentActionContext;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\CrmMemberRepository;
use App\Services\Members\Consents\ConsentCommandService;
use App\Services\Members\Consents\ConsentQueryService;

class CrmMemberConsentController extends Controller
{
    public function __construct(
        private readonly CrmMemberRepository   $crmMemberRepository,
        private readonly ConsentQueryService   $queryService,
        private readonly ConsentCommandService $commandService,
    )
    {
        parent::__construct();
    }

    public function index(int $memberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        return $this->resourceResponse([
            'items' => $this->groupConsents($this->queryService->getMemberConsents($member)),
        ]);
    }

    private function groupConsents(array $consents): array
    {
        $groupedConsents = [];

        foreach ($consents as $consent) {
            $type = $consent['consent_type'];
            $isLocked = $type['category'] === 'essential' || !empty($type['required']);
            $consent['is_locked'] = $isLocked;

            if ($isLocked) {
                $consent['is_granted'] = true;
            }

            $category = $type['category'];
            $groupedConsents[$category] ??= [];
            $groupedConsents[$category][] = $consent;
        }

        return $groupedConsents;
    }

    public function update(Request $request, int $memberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->errorResponse('Member not found', 404);
        }

        $consents = $request->input('consents', []);
        $context = ConsentActionContext::fromRequest($request, 'crm');
        $results = [];

        foreach ($consents as $consentCode => $granted) {
            try {
                $results[$consentCode] = $granted
                    ? $this->commandService->grantConsent($member, $consentCode, $context)
                    : $this->commandService->revokeConsent($member, $consentCode, $context);
            } catch (\Exception $e) {
                $results[$consentCode] = ['error' => $e->getMessage()];
            }
        }

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Consent preferences updated successfully',
            'results' => $results,
            'items' => $this->groupConsents($this->queryService->getMemberConsents($member)),
        ]);
    }
}
