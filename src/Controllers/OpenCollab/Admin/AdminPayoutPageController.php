<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * Renders the admin payout management page.
 * All payout data is loaded client-side via PayoutController::adminIndex.
 *
 * Routes:
 *   GET /admin/payouts
 */
class AdminPayoutPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /admin/payouts
     */
    public function index()
    {
        $this->requireAdmin();

        return $this->view('open-collab.admin.payouts.index', [
            'pageTitle' => 'Payout Management',
            'activeNav' => 'payouts',
            'breadcrumbs' => [['label' => 'Payouts']],
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