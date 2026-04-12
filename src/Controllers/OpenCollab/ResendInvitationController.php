<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\InvitationResendService;

class ResendInvitationController extends Controller
{
    public function __construct(
        private readonly InvitationResendService $resendService
    )
    {
        parent::__construct();
    }

    public function resend(Request $request): JsonResponse
    {
        $email = trim($request->get('email') ?? '');
        $siteId = SiteContext::getId();

        $this->resendService->handle($email, $siteId);

        return $this->resourceResponse([
            'message' => 'If an invitation exists for that address, a fresh link has been sent.',
        ]);
    }
}