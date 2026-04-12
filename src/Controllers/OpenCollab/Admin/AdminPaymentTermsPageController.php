<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\PaymentTermsService;

/**
 * Renders the admin payment terms configuration page.
 *
 * Routes:
 *   GET /admin/payment-terms
 */
class AdminPaymentTermsPageController extends Controller
{
    public function __construct(
        private readonly PaymentTermsService $paymentTermsService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/payment-terms
     */
    public function index()
    {
        $this->requireAdmin();

        $terms = $this->paymentTermsService->forSite(SiteContext::getId());

        return $this->view('open-collab.admin.payment-terms.index', [
            'terms' => $terms,
            'pageTitle' => 'Payment Terms',
            'activeNav' => 'payment-terms',
            'breadcrumbs' => [['label' => 'Payment Terms']],
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