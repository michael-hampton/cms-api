<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\ContributorRequestService;

/**
 * Admin HTML view for contributor access request queue.
 *
 * Routes:
 *   GET /{site}/open-collab/admin/contributor-requests
 */
class AdminContributorRequestPageController extends Controller
{
    public function __construct(
        private readonly ContributorRequestService $requestService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /{site}/open-collab/admin/contributor-requests
     */
    public function index()
    {
        $this->requireAdmin();

        $requests = $this->requestService->pendingForSite(SiteContext::getId());

        return $this->view('open-collab.admin.contributor-requests.index', [
            'requests' => $requests,
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

    private function requireAdmin(): void
    {
        $user = Auth::getUser();
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'agent'], true)) {
            header('Location: /login');
            exit;
        }
    }
}