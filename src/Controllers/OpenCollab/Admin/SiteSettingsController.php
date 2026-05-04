<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\SiteContext;
use App\Requests\UpdateSiteSettingsRequest;
use App\Services\OpenCollab\SiteService;

class SiteSettingsController extends Controller
{
    private SiteService $siteService;

    public function __construct(SiteService $siteService)
    {
        $this->siteService = $siteService;

        parent::__construct();
    }

    public function show(): mixed
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        return $this->view('open-collab.admin.sites.settings', [
            'pageTitle' => 'Site Settings',
            'activeNav' => 'site_settings',
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => "/{$site->slug}/open-collab/admin"],
                ['label' => 'Site Settings'],
            ],
            'site' => $site->slug,
            'currentSite' => $site,
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): mixed
    {
        $site = SiteContext::get();

        if (!$site) {
            return $this->errorResponse('Site not found', 404);
        }

        try {
            $data = $request->validated();
            $this->siteService->updateSiteSettings($site->id, $data);

            return $this->redirect(
                "/{$site->slug}/open-collab/admin/sites/settings",
                ['flash_success' => 'Site settings saved.']
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}