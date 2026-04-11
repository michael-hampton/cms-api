<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ViolationRepository;

/**
 * Renders admin HTML views for violation management.
 *
 * Routes:
 *   GET /admin/violations   — all site violations
 */
class AdminViolationPageController extends Controller
{
    public function __construct(
        private readonly ViolationRepository $violationRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/violations
     */
    public function index()
    {
        $this->requireAdmin();

        $violations = $this->violationRepository->forSite(SiteContext::getId(), 50);

        $items = is_array($violations) ? ($violations['data'] ?? $violations) : $violations;
        if (is_object($items) && method_exists($items, 'toArray')) {
            $items = $items->toArray();
        }

        return $this->view('open-collab.admin.violations.index', [
            'violations' => $items,
            'pageTitle' => 'Violations',
            'activeNav' => 'violations',
            'breadcrumbs' => [['label' => 'Violations']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }

    private function requireAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role ?? '', ['admin', 'agent'], true)) {
            header('Location: /login');
            exit;
        }
    }
}