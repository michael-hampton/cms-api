<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\PayoutRepository;

/**
 * Renders admin HTML views for payout management.
 *
 * Routes:
 *   GET /admin/payouts   — all site payouts with approve/reject actions
 */
class AdminPayoutPageController extends Controller
{
    public function __construct(
        private readonly PayoutRepository $payoutRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/payouts
     */
    public function index()
    {
        $this->requireAdmin();

        $siteId = SiteContext::getId();
        $pending = $this->payoutRepository->pendingForSite($siteId);
        $all = $this->payoutRepository->forSite($siteId, 50);

        $allItems = is_array($all) ? ($all['data'] ?? $all) : $all;
        if (is_object($allItems) && method_exists($allItems, 'toArray')) {
            $allItems = $allItems->toArray();
        }

        return $this->view('open-collab.admin.payouts.index', [
            'pendingPayouts' => $pending,
            'allPayouts' => $allItems,
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