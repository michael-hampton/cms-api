<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * GET /admin/contracts
 */
class AdminContractPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->requireAdmin();

        return $this->view('open-collab.admin.contracts.index', [
            'pageTitle' => 'Contributor Contracts',
            'activeNav' => 'contracts',
            'breadcrumbs' => [['label' => 'Contracts']],
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