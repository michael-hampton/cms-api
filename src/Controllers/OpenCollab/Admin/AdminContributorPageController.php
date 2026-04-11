<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\AdminContributorRepository;

/**
 * Renders admin HTML views for contributor management.
 *
 * Routes:
 *   GET /admin/contributors         — search/list
 *   GET /admin/contributors/{id}    — profile view
 */
class AdminContributorPageController extends Controller
{
    public function __construct(
        private readonly AdminContributorRepository $contributorRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/contributors
     */
    public function index(Request $request)
    {
        $this->requireAdmin();

        $query = $request->get('q');
        $results = $this->contributorRepository->searchForSite(SiteContext::getId(), $query, 25);

        $items = $results['data'] ?? $results;
        if (is_object($items) && method_exists($items, 'toArray')) {
            $items = $items->toArray();
        }

        return $this->view('open-collab.admin.contributors.index', [
            'contributors' => $items,
            'query' => $query ?? '',
            'pageTitle' => 'Contributors',
            'activeNav' => 'contributors',
            'breadcrumbs' => [['label' => 'Contributors']],
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

    /**
     * GET /admin/contributors/{id}
     */
    public function show(int $id)
    {
        $this->requireAdmin();

        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            return $this->errorView(404, 'Contributor not found.');
        }

        $values = is_array($contributor) ? $contributor : $contributor->toArray();

        return $this->view('open-collab.admin.contributors.show', [
            'contributor' => $values,
            'pageTitle' => 'Contributor: ' . ($values['name'] ?? ''),
            'activeNav' => 'contributors',
            'breadcrumbs' => [
                ['label' => 'Contributors', 'url' => '/admin/contributors'],
                ['label' => $values['name'] ?? 'Profile'],
            ],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }

    private function errorView(int $status, string $message)
    {
        http_response_code($status);
        return $this->view('errors.' . $status, ['message' => $message]);
    }
}