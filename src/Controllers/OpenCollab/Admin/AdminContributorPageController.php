<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\AdminContributorRepository;
use App\Repositories\OpenCollab\InvitationRepository;

/**
 * Renders admin HTML views for contributor and invitation management.
 *
 * Routes:
 *   GET /admin/contributors               — search/list
 *   GET /admin/contributors/{id}          — profile view
 *   GET /admin/contributors/{id}/invitations — contributor's invitation history
 */
class AdminContributorPageController extends Controller
{
    public function __construct(
        private readonly AdminContributorRepository $contributorRepository,
        private readonly InvitationRepository $invitationRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /{site}/open-collab/admin/contributors
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

    /**
     * GET /{site}/open-collab/admin/contributors/{id}
     */
    public function show(int $id)
    {
        $this->requireAdmin();

        $contributor = $this->contributorRepository->findContributorForSite($id, SiteContext::getId());

        if (!$contributor) {
            http_response_code(404);
            return $this->view('errors.404', ['message' => 'Contributor not found.']);
        }

        $values = is_array($contributor) ? $contributor : $contributor->toArray();

        // Load invitation history for this contributor's email
        $invitations = collect([]);
        if (!empty($values['email'])) {
            $invitations = $this->invitationRepository->getAllForSite(SiteContext::getId())
                ->filter(fn($inv) => $inv->email === $values['email']);
        }

        return $this->view('open-collab.admin.contributors.show', [
            'contributor' => $values,
            'invitations' => $invitations,
            'pageTitle' => 'Contributor: ' . ($values['name'] ?? ''),
            'activeNav' => 'contributors',
            'breadcrumbs' => [
                ['label' => 'Contributors', 'url' => '/' . SiteContext::slug() . '/open-collab/admin/contributors'],
                ['label' => $values['name'] ?? 'Profile'],
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