<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\InvitationResendService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Framework\Authorization\Auth;

class ResendInvitationController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly InvitationResendService $resendService,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
        parent::__construct();
    }

    public function resend(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['creator.invite', 'site.members'])) {
            return $response;
        }

        $email = trim($request->get('email') ?? '');
        $siteId = SiteContext::getId();

        $this->resendService->handle($email, $siteId);

        return $this->resourceResponse([
            'message' => 'If an invitation exists for that address, a fresh link has been sent.',
        ]);
    }
}
