<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ActivityRepository;

/**
 * Renders the admin site-wide activity feed.
 *
 * Routes:
 *   GET /admin/activity
 */
class AdminActivityPageController extends Controller
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/activity
     */
    public function index()
    {
        $this->requireAdmin();

        $events = $this->activityRepository->forSite(SiteContext::getId(), 100);

        return $this->view('open-collab.admin.activity.index', [
            'events' => $events,
            'pageTitle' => 'Activity Feed',
            'activeNav' => 'activity',
            'breadcrumbs' => [['label' => 'Activity Feed']],
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