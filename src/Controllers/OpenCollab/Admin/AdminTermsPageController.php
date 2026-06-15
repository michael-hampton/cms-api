<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
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
        if ($response = $this->authorizeSitePagePermissions(['terms.view', 'terms.create', 'terms.publish'])) {
            return $response;
        }

        $userId = (int)Auth::id();
        $siteId = (int)SiteContext::getId();
        $selectedId = (int)$request->input('terms_id', 0);

        return $this->view('open-collab.admin.terms.index', [
            'canCreateTerms' => $this->authorization->allows($userId, $siteId, 'terms.create'),
            'canEditTerms' => $this->authorization->allows($userId, $siteId, 'terms.edit'),
            'canPublishTerms' => $this->authorization->allows($userId, $siteId, 'terms.publish'),
            'termsVersions' => $this->repository->allForSite($siteId),
            'selectedTerms' => $selectedId > 0
                ? $this->repository->findForSite($selectedId, $siteId)
                : null,
            'pageTitle' => 'Terms & Conditions',
            'activeNav' => 'terms',
            'breadcrumbs' => [['label' => 'Terms & Conditions']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
            'siteSlug' => SiteContext::slug(),
        ]);
    }
}
