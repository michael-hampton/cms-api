<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Controllers\OpenCollab\Concerns\ResolvesUiComponents;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * GET /admin/contracts
 */
class AdminContractPageController extends Controller
{
    use AuthorizesSitePagePermissions;
    use ResolvesUiComponents;

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

        return $this->view('open-collab.admin.contracts.index', [
            'allowedComponentKeys' => $this->allowedUiComponentKeysForSurface('contract.index'),
            'pageTitle' => 'Contributor Contracts',
            'activeNav' => 'contracts',
            'breadcrumbs' => [['label' => 'Contracts']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
