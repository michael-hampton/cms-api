<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\DTO\Consents\ConsentActionContext;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Framework\Support\SiteContext;
use App\Models\ConsentType;
use App\Services\Members\Consents\ConsentCommandService;
use App\Services\Members\Consents\ConsentQueryService;

class MemberConsentController extends Controller
{
    public function __construct(
        private readonly ConsentCommandService $commandService,
        private readonly ConsentQueryService   $queryService
    )
    {
        parent::__construct();
    }

    /**
     * Show consent preferences page
     */
    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $consents = $this->queryService->getMemberConsents($member);

        $groupedConsents = [];
        foreach ($consents as $consent) {
            $category = $consent['consent_type']['category'];
            if (!isset($groupedConsents[$category])) {
                $groupedConsents[$category] = [];
            }
            $groupedConsents[$category][] = $consent;
        }

        return $this->view('member/consent/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'consents' => $groupedConsents
        ]);
    }

    /**
     * Grant specific consent
     */
    public function grant(Request $request, string $consentCode)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $context = ConsentActionContext::fromRequest($request, 'web');

        try {
            $consent = $this->commandService->grantConsent($member, $consentCode, $context);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Consent granted successfully',
                'consent' => [
                    'is_granted' => $consent->is_granted,
                    'granted_at' => $consent->granted_at->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to grant consent: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke specific consent
     */
    public function revoke(Request $request, string $consentCode)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $context = ConsentActionContext::fromRequest($request, 'web');

        try {
            $this->commandService->revokeConsent($member, $consentCode, $context);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Consent revoked successfully'
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to revoke consent: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show consent audit trail
     */
    public function auditTrail(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $consentCode = $request->input('consent_code');

        $auditTrail = $this->queryService->getAuditTrail($member, $consentCode);

        return $this->view('member/consent/audit-trail', [
            'member' => $member,
            'site' => SiteContext::get(),
            'auditTrail' => $auditTrail
        ]);
    }


    /**
     * Download consent data (GDPR data portability)
     */
    public function downloadData()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();

        $data = [
            'member' => [
                'id' => $member->id,
                'email' => $member->email,
                'name' => $member->getFullNameAttribute()
            ],
            'consents' => $this->queryService->getMemberConsents($member),
            'audit_trail' => $this->queryService->getAuditTrail($member),
            'exported_at' => now_datetime()->format('Y-m-d H:i:s')
        ];

        $filename = 'consent_data_' . $member->id . '_' . date('Y-m-d') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        return $this->resourceResponse($data);
    }

    /**
     * Create withdrawal request
     */
    public function createWithdrawalRequest(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $type = $request->input('type');
        $consentTypes = $request->input('consent_types', []);

        try {
            $withdrawalRequest = \App\Models\ConsentWithdrawalRequest::create([
                'member_id' => $member->id,
                'type' => $type,
                'consent_types' => $consentTypes,
                'status' => 'pending',
                'requested_at' => now()
            ]);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'request_id' => $withdrawalRequest->id
            ]);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to submit withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check consent status (API endpoint)
     */
    public function checkConsent(Request $request, string $consentCode)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::getMember();
        $hasConsent = $this->queryService->hasConsent($member, $consentCode);

        return $this->resourceResponse([
            'success' => true,
            'consent_code' => $consentCode,
            'has_consent' => $hasConsent
        ]);
    }

    /**
     * Accept consent banner/notice
     */
    public function acceptBanner(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 302);
        }

        $member = MemberAuth::getMember();
        $consentsToGrant = $request->input('consents', []);
        $siteSlug = SiteContext::slug();
        $context = ConsentActionContext::fromRequest($request, 'web');

        Session::put('consent_banner_shown_' . $siteSlug, true);

        try {
            $results = [];
            foreach ($consentsToGrant as $consentCode) {
                $results[$consentCode] = $this->commandService->grantConsent(
                    $member,
                    $consentCode,
                    $context
                );
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Consent preferences saved',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to save consent preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getOptionalConsentTypes()
    {
        $consentTypes = ConsentType::where('is_active', true)
            ->where('required', false)
            ->get()
            ->map(function ($type) {
                return [
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description,
                    'category' => $type->category
                ];
            })
            ->toArray();

        return $this->jsonResponse([
            'success' => true,
            'consent_types' => $consentTypes
        ]);
    }
}