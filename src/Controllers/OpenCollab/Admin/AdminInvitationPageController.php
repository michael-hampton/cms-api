<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\InvitationRepository;

/**
 * Renders admin HTML views for invitation management.
 *
 * Routes:
 *   GET /admin/invitations   — list all invitations with create form
 */
class AdminInvitationPageController extends Controller
{
    public function __construct(
        private readonly InvitationRepository $invitationRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/invitations
     */
    public function index()
    {
        $invitations = $this->invitationRepository->getAllForSite(SiteContext::getId());

        return $this->view('open-collab.admin.invitations.index', [
            'invitations' => $invitations,
            'pageTitle' => 'Invitations',
            'activeNav' => 'invitations',
            'breadcrumbs' => [['label' => 'Invitations']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}