<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

class AdminTermsPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly TermsVersionRepository $repository,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        if ($response = $this->authorizeSitePagePermissions(['terms.edit', 'terms.publish', 'terms.archive'])) {
            return $response;
        }

        $siteId = SiteContext::getId();
        $selectedId = (int)$request->input('terms_id', 0);

        return $this->view('open-collab.admin.terms.index', [
            'termsVersions' => $this->repository->allForSite($siteId),
            'selectedTerms' => $selectedId > 0
                ? $this->repository->findForSite($selectedId, $siteId)
                : null,
            'siteSlug' => SiteContext::slug(),
        ]);
    }
}
