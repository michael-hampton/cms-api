<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\Surfaces\SurfaceResolver;

/**
 * Renders the admin earnings disputes management page.
 * Data is loaded client-side via the EarningsDisputeController API endpoints.
 *
 * Routes:
 *   GET /admin/disputes
 */
class AdminDisputePageController extends Controller
{
    public function __construct(
        private readonly SurfaceResolver $surfaceResolver,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/disputes
     */
    public function index()
    {
        $site = SiteContext::slug();
        $surface = 'admin.disputes.index';
        $sections = $this->surfaceResolver->manifest($surface, $site);

        return $this->view('open-collab.admin.disputes.index', [
            'surface' => $surface,
            'sections' => $sections,
            'surfaceContext' => [],
            'extraHead' => '<link rel="stylesheet" href="' . asset('open-collab-surface-widgets.css', 'css') . '">',
            'extraScripts' => '<script>window.OPEN_COLLAB_ADMIN_SURFACE = ' . json_encode([
                'surface' => $surface,
                'site' => $site,
                'sections' => $sections,
            ]) . ';</script><script src="' . asset('open-collab-admin-surface-widgets.js', 'js') . '"></script>',
            'pageTitle' => 'Earnings Disputes',
            'activeNav' => 'disputes',
            'breadcrumbs' => [['label' => 'Earnings Disputes']],
            'currentUser' => Auth::user(),
            'site' => $site,
        ]);
    }
}
