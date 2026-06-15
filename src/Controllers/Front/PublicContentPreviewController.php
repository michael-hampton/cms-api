<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;

final class PublicContentPreviewController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function show(string $slug): Response
    {
        $siteSlug = SiteContext::slug();

        return $this->view('public-content-v2/page', [
            'siteSlug' => $siteSlug,
            'contentSlug' => $slug,
            'apiUrl' => sprintf(
                '/api/v1/%s/content/%s',
                rawurlencode($siteSlug),
                rawurlencode($slug),
            ),
        ]);
    }
}
