<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * Admin HTML view for contributor access request queue.
 * Data is loaded client-side via ContributorRequestController::index,
 * ::approve and ::reject.
 *
 * Routes:
 *   GET /{site}/open-collab/admin/contributor-requests
 */
class AdminContributorRequestPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /{site}/open-collab/admin/contributor-requests
     */
    public function index()
    {
        return $this->view('open-collab.admin.contributor-requests.index', [
            'pageTitle' => 'Access Requests',
            'activeNav' => 'contributors',
            'breadcrumbs' => [
                ['label' => 'Contributors', 'url' => '/' . SiteContext::slug() . '/open-collab/admin/contributors'],
                ['label' => 'Access Requests'],
            ],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}