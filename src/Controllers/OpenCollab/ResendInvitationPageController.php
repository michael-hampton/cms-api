<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;

/**
 * GET /{site}/open-collab/resend-invitation
 * Renders the self-service resend invitation form.
 */
class ResendInvitationPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        return $this->view('open-collab.resend-invitation', [
            'site' => SiteContext::slug(),
        ]);
    }
}