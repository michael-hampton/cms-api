<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\WidgetResolver;
use App\Services\OpenCollab\Dashboard\WidgetResponse;

class DashboardPageNewController extends Controller
{
    public function __construct(
        private readonly WidgetResolver $widgetResolver,
        private readonly WidgetRegistry $widgetRegistry,
    )
    {
        parent::__construct();
    }

    /**
     * GET /contributor/dashboard
     */
    public function index()
    {
        $user = User::hydrateStatic(Auth::getUser());
        $widgets = $this->widgetResolver->resolveForUser($user);

        // Pass only keys and titles to the view.
        // Actual data is fetched per-widget via the JS widget manager.
        $widgetManifest = array_map(
            fn($w) => ['key' => $w->key(), 'title' => $w->title()],
            $widgets
        );

        return $this->view('open-collab.dashboard-new.show', [
            'widgets' => $widgetManifest,
            'currentUser' => $user,
            'site' => SiteContext::slug(),
        ]);
    }

    public function getWidget(string $slug)
    {
        $user   = User::hydrateStatic(Auth::getUser());
        $widget = $this->widgetRegistry->get($slug);

        $response = WidgetResponse::make(
            $widget->key(),
            $widget->title(),
            $widget->data($user),
        );

        return $this->resourceResponse($response->toArray());
    }
}