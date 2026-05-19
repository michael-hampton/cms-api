<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Services\Gdpr\MemberAnonymisationService;
use InvalidArgumentException;
use RuntimeException;

/**
 * POST /api/crm/members/{id}/forget
 *
 * Executes Right to be Forgotten anonymisation for a member.
 * Requires admin authentication.
 * This action is irreversible.
 */
class RtbfController extends Controller
{
    public function __construct(
        private readonly MemberAnonymisationService $anonymisationService,
    ) {
        parent::__construct();
    }

    public function forget(Request $request, int $memberId): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $adminId = Auth::id();
        $siteId  = SiteContext::getId();

        $member = Member::where('id', $memberId)
            //->where('site_id', $siteId)
            ->first();

        if (!$member) {
            return $this->errorResponse('Member not found.', 404);
        }

        // Require explicit confirmation in the request body to prevent
        // accidental triggering via mis-routed requests.
        $confirmed = $request->get('confirmed');
        if ($confirmed !== true && $confirmed !== 'true' && $confirmed !== 1 && $confirmed !== '1') {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'This action is irreversible. Pass confirmed=true to proceed.',
            ], 422);
        }

        try {
            $this->anonymisationService->anonymise($memberId, $adminId);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (\Exception $e) {
            return $this->errorResponse('Anonymisation failed. Please try again.', 500);
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Member data has been anonymised in compliance with GDPR Right to be Forgotten.',
        ]);
    }
}