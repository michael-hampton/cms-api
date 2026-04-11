<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * GET /admin/guidelines
 */
class AdminGuidelinesPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->requireAdmin();

        return $this->view('open-collab.admin.guidelines.index', [
            'pageTitle' => 'Brand Guidelines',
            'activeNav' => 'guidelines',
            'breadcrumbs' => [['label' => 'Guidelines']],
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