<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * GET /admin/contracts
 */
class AdminContractPageController extends Controller
{
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function index()
    {
        if ($response = $this->authorizeSitePagePermissions(['contract.view', 'contract.create', 'contract.publish'])) {
            return $response;
        }

        $userId = (int)Auth::id();
        $siteId = (int)SiteContext::getId();

        return $this->view('open-collab.admin.contracts.index', [
            'canCreateContract' => $this->authorization->allows($userId, $siteId, 'contract.create'),
            'canEditContract' => $this->authorization->allows($userId, $siteId, 'contract.edit'),
            'canPublishContract' => $this->authorization->allows($userId, $siteId, 'contract.publish'),
            'canDeleteContract' => $this->authorization->allows($userId, $siteId, 'contract.delete'),
            'canCloneContract' => $this->authorization->allows($userId, $siteId, 'contract.clone'),
            'pageTitle' => 'Contributor Contracts',
            'activeNav' => 'contracts',
            'breadcrumbs' => [['label' => 'Contracts']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
