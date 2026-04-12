<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Repositories\OpenCollab\ViolationRepository;

/**
 * Admin HTML views for violation management.
 *
 * Routes:
 *   GET /{site}/open-collab/admin/violations                      — site-wide list
 *   GET /{site}/open-collab/admin/contributors/{id}/violations    — per-contributor
 */
class AdminViolationPageController extends Controller
{
    public function __construct(
        private readonly ViolationRepository        $violationRepository,
        private readonly AdminContributorRepository $contributorRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /{site}/open-collab/admin/violations
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

    /**
     * GET /{site}/open-collab/admin/contributors/{id}/violations
     */
    public function contributor(int $id)
    {
        $this->requireAdmin();

        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            http_response_code(404);
            return $this->view('errors.404', ['message' => 'Contributor not found.']);
        }

        $values = is_array($contributor) ? $contributor : $contributor->toArray();

        $violations = $this->violationRepository->forContributor($id, SiteContext::getId());

        return $this->view('open-collab.admin.violations.contributor', [
            'contributor' => $values,
            'violations' => $violations,
            'pageTitle' => 'Violations: ' . ($values['name'] ?? ''),
            'activeNav' => 'violations',
            'breadcrumbs' => [
                ['label' => 'Contributors', 'url' => '/' . SiteContext::slug() . '/open-collab/admin/contributors'],
                ['label' => $values['name'] ?? 'Profile', 'url' => '/' . SiteContext::slug() . '/open-collab/admin/contributors/' . $id],
                ['label' => 'Violations'],
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