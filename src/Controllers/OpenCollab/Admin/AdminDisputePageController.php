<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\EarningsDisputeRepository;

/**
 * Renders the admin earnings disputes management page.
 *
 * Routes:
 *   GET /admin/disputes
 */
class AdminDisputePageController extends Controller
{
    public function __construct(
        private readonly EarningsDisputeRepository $disputeRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/disputes
     */
    public function index()
    {
        $this->requireAdmin();

        $disputes = $this->disputeRepository->openForSite(SiteContext::getId());

        return $this->view('open-collab.admin.disputes.index', [
            'disputes' => $disputes,
            'pageTitle' => 'Earnings Disputes',
            'activeNav' => 'disputes',
            'breadcrumbs' => [['label' => 'Earnings Disputes']],
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