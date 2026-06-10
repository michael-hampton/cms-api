<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\WidgetResolver;
use App\Services\OpenCollab\Dashboard\WidgetResponse;
use App\Services\OpenCollab\SitePermissionResolver;

class DashboardPageNewController extends Controller
{
    public function __construct(
        private readonly WidgetResolver         $widgetResolver,
        private readonly WidgetRegistry         $widgetRegistry,
        private readonly SitePermissionResolver $permissionResolver,
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        $user = Auth::getUser();

        return $this->jsonResponse([
            'widgets' => $this->widgetResolver->availableForUser(User::hydrateStatic($user)),
        ]);
    }

    /**
     * GET /contributor/dashboard
     */
    public function show()
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
        $user = User::hydrateStatic(Auth::getUser());
        $widget = $this->widgetRegistry->get($slug);
        $siteId = (int)SiteContext::getId();

        foreach ($this->widgetRegistry->permissionsFor($slug) as $permission) {
            if (!$this->permissionResolver->allows($user->id, $siteId, $permission)) {
                return $this->errorResponse('Forbidden.', 403);
            }
        }

        $response = WidgetResponse::make(
            $widget->key(),
            $widget->title(),
            $widget->data($user),
        );

        return $this->resourceResponse($response->toArray());
    }
}
