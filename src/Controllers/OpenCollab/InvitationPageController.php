<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;

class InvitationPageController extends Controller
{
    /**
     * GET /admin/invitations
     * Show the form to invite a new contributor.
     */
    public function index()
    {
        return $this->view('open-collab.invitations.index', [
            'site' => SiteContext::slug()
        ]);
    }

    public function showAcceptForm(string $token)
    {
        // You could optionally validate the token here via Repository
        // to show a 404 early if it's expired.

        return $this->view('open-collab.invitations.accept', [
            'token' => $token,
            'site' => SiteContext::slug()
        ]);
    }
}