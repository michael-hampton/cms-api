<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Services\PublicContent\PublicContentRollout;

final class PublicContentPreviewController extends Controller
{
    public function __construct(private readonly PublicContentRollout $rollout)
    {
        parent::__construct();
    }

    public function show(string $slug): Response
    {
        if (!$this->rollout->previewEnabled()) {
            return $this->notFound('Public content preview is disabled.');
        }

        $siteId = SiteContext::getId();
        $siteSlug = SiteContext::slug();

        return $this->view('public-content-v2/page', [
            'site' => SiteContext::get(),
            'siteSlug' => $siteSlug,
            'contentSlug' => $slug,
            'menu' => Menu::where('is_active', true)
                ->where('site_id', $siteId)
                ->where('menu_type', 'header')
                ->with(['items'])
                ->first(),
            'footerMenu' => Menu::where('is_active', true)
                ->where('site_id', $siteId)
                ->where('menu_type', 'footer')
                ->with(['items'])
                ->first(),
            'apiUrl' => sprintf(
                '/api/v1/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode($slug),
            ),
        ]);
    }
}
