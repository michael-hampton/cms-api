<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\InvitationRepository;

/**
 * InvitationPageController
 * Renders HTML views for invitation-related pages.
 * Validates the token early so the view can show the correct error state
 * without a double round-trip to the API.
 */
class InvitationPageController extends Controller
{
    public function __construct(
        private readonly InvitationRepository $invitationRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /invite/{token}
     * Shows the accept form, or an error state if the token is invalid.
     */
    public function showAcceptForm(string $token)
    {
        $invitation = $this->invitationRepository->findByToken($token);

        // Determine token state for the view
        if (!$invitation) {
            $tokenState = 'invalid';
        } else {
            $status = $invitation->resolveStatus();
            $tokenState = $status->value; // 'pending' | 'used' | 'expired' | 'revoked'
        }

        return $this->view('open-collab.invitations.accept', [
            'token' => $token,
            'tokenState' => $tokenState,
            'invitation' => $tokenState === 'pending' ? $invitation : null,
            'site' => SiteContext::slug(),
        ]);
    }

    /**
     * GET /admin/invitations
     * Admin panel: list + create invitations.
     */
    public function index()
    {
        $invitations = $this->invitationRepository->getAllForSite(SiteContext::getId());

        return $this->view('open-collab.invitations.index', [
            'invitations' => $invitations,
            'site' => SiteContext::slug(),
        ]);
    }
}