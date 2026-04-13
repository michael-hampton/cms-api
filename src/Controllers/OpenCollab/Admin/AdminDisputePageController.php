<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * Renders the admin earnings disputes management page.
 * Data is loaded client-side via the EarningsDisputeController API endpoints.
 *
 * Routes:
 *   GET /admin/disputes
 */
class AdminDisputePageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /admin/disputes
     */
    public function index()
    {
        $this->requireAdmin();

        return $this->view('open-collab.admin.disputes.index', [
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