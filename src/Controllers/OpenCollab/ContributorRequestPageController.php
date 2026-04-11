<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;
use App\Models\Site;

/**
 * GET /{site}/open-collab/request-access
 * Renders the public self-service contributor registration form.
 */
class ContributorRequestPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function show()
    {
        $site = Site::find(SiteContext::getId());
        $siteSlug = SiteContext::slug();

        return $this->view('open-collab.contributor-request', [
            'site' => $siteSlug,
            'requiresApproval' => (bool)($site?->require_invite_approval ?? true),
            'submitted' => false,
        ]);
    }
}