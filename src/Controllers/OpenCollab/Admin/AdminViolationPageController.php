<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\ResolvesUiComponents;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\AdminContributorRepository;

/**
 * Admin HTML views for violation management.
 * Site-wide list data is loaded client-side via ViolationController::siteIndex.
 * Per-contributor view still loads server-side for the detail page.
 *
 * Routes:
 *   GET /{site}/open-collab/admin/violations                      — site-wide list (API-driven)
 *   GET /{site}/open-collab/admin/contributors/{id}/violations    — per-contributor (server-side)
 */
class AdminViolationPageController extends Controller
{
    use ResolvesUiComponents;

    public function __construct(
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
        return $this->view('open-collab.admin.violations.index', [
            'allowedComponentKeys' => $this->allowedUiComponentKeysForSurface('violations.index'),
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
        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            http_response_code(404);
            return $this->view('errors.404', ['message' => 'Contributor not found.']);
        }

        $values = is_array($contributor) ? $contributor : $contributor->toArray();

        // Per-contributor violations are loaded server-side on this detail page
        // (the contributor violations view already loads from its own API call via JS).
        return $this->view('open-collab.admin.violations.contributor', [
            'contributor' => $values,
            'violations' => collect([]), // loaded client-side in the view JS
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
}
