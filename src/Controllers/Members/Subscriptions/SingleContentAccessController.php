<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\SingleContentAccess;
use App\Services\Subscriptions\SingleContentAccessService;

class SingleContentAccessController extends Controller
{
    public function __construct(
        private readonly SingleContentAccessService $accessService
    )
    {
        parent::__construct();
    }

    /**
     * Show purchase page for single content
     */
    public function show(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirectResponse('', 401);
        }

        $contentType = $request->get('type');
        $contentId = (int)$request->get('id');

        if (!$contentType || !$contentId) {
            return $this->redirectResponse('', 400);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        if (!MemberAuth::check()) {
            return $this->view('member/single-access/already-purchased', [
                'site' => SiteContext::get(),
                'access' => false,
                'content_type' => $contentType
            ]);
        }

        // Check if already has access
        $accessCheck = $this->accessService->checkAccess($member->id, $contentType, $contentId, $siteId);

        if ($accessCheck['has_access']) {
            return $this->view('member/single-access/already-purchased', [
                'site' => SiteContext::get(),
                'access' => $accessCheck['access'],
                'content_type' => $contentType
            ]);
        }

        // Get pricing details
        $pricing = $this->accessService->getContentAccessDetails($contentType, $contentId);

        // Get content details
        $content = $this->getContentDetails($contentType, $contentId);

        if (!$content) {
            return $this->redirectResponse('', 404);
        }

        return $this->view('member/single-access/purchase', [
            'site' => SiteContext::get(),
            'member' => $member,
            'content' => $content,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'pricing' => $pricing
        ]);
    }

    /**
     * Helper to get content details
     */
    private function getContentDetails(string $contentType, int $contentId)
    {
        return match ($contentType) {
            SingleContentAccess::CONTENT_TYPE_PAGE,
            SingleContentAccess::CONTENT_TYPE_REPORT => \App\Models\Page::find($contentId),
            SingleContentAccess::CONTENT_TYPE_NEWSLETTER => \App\Models\Newsletter::find($contentId),
            default => null
        };
    }

    /**
     * Process purchase
     */
    public function purchase(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Please log in to continue'
            ], 401);
        }

        $member = MemberAuth::getMember();

        // Double check member exists
        if (!$member) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $siteId = SiteContext::getId();

        $contentType = $request->input('content_type');
        $contentId = (int)$request->input('content_id');

        $validTypes = [
            SingleContentAccess::CONTENT_TYPE_PAGE,
            SingleContentAccess::CONTENT_TYPE_NEWSLETTER,
            SingleContentAccess::CONTENT_TYPE_REPORT
        ];

        if (!$contentType || !$contentId || !in_array($contentType, $validTypes)) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Invalid content type'
            ], 400);
        }

        try {
            $pricing = $this->accessService->getContentAccessDetails($contentType, $contentId);

            $result = $this->accessService->purchaseAccess(
                $member->id,
                $siteId,
                $contentType,
                $contentId,
                $pricing['price'],
                $pricing['currency'],
                $pricing['duration_days']
            );

            return $this->resourceResponse($result);

        } catch (\Exception $e) {
            // Check if this is an "already purchased" error
            $statusCode = 500;
            if (strpos(strtolower($e->getMessage()), 'already') !== false) {
                $statusCode = 400;
            }

            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Complete purchase after payment
     */
    public function complete(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $paymentIntentId = $request->input('payment_intent_id');

        if (!$paymentIntentId) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Missing payment information'
            ], 400);
        }

        try {
            $result = $this->accessService->completeAccessPurchase($paymentIntentId);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Access granted successfully!',
                'access_token' => $result['access']->access_token
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * View purchased access list
     */
    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $member = MemberAuth::getMember();

        // Double check member exists
        if (!$member) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $siteId = SiteContext::getId();

        $accessList = $this->accessService->getMemberActiveAccess($member->id, $siteId);

        return $this->view('member/single-access/index', [
            'site' => SiteContext::get(),
            'member' => $member,
            'access_list' => $accessList
        ]);
    }
}