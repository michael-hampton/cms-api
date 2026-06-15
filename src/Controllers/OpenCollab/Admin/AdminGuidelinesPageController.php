<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * GET /admin/guidelines
 */
class AdminGuidelinesPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function index()
    {
        if ($response = $this->authorizeSitePagePermissions(['guidelines.view', 'guidelines.create', 'guidelines.publish'])) {
            return $response;
        }

        $userId = (int)Auth::id();
        $siteId = (int)SiteContext::getId();

        return $this->view('open-collab.admin.guidelines.index', [
            'canCreateGuideline' => $this->authorization->allows($userId, $siteId, 'guidelines.create'),
            'canEditGuideline' => $this->authorization->allows($userId, $siteId, 'guidelines.edit'),
            'canPublishGuideline' => $this->authorization->allows($userId, $siteId, 'guidelines.publish'),
            'canDeleteGuideline' => $this->authorization->allows($userId, $siteId, 'guidelines.delete'),
            'canCloneGuideline' => $this->authorization->allows($userId, $siteId, 'guidelines.clone'),
            'pageTitle' => 'Brand Guidelines',
            'activeNav' => 'guidelines',
            'breadcrumbs' => [['label' => 'Guidelines']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
