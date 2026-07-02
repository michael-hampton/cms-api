<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Services\PublicContent\Diagnostics\PublicContentDiagnosticsDashboardViewModel;

final class PublicContentDiagnosticsDashboardController extends Controller
{
    public function __construct(
        private readonly PublicContentDiagnosticsDashboardViewModel $viewModel,
    ) {
        parent::__construct();
    }

    public function show(): Response
    {
        return Response::view(
            'public-content-v2/diagnostics-dashboard',
            $this->viewModel->build(SiteContext::getId(), SiteContext::slug()),
        );
    }
}