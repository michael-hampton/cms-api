<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\ResolvesUiComponents;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * GET /admin/guidelines
 */
class AdminGuidelinesPageController extends Controller
{
    use ResolvesUiComponents;

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('open-collab.admin.guidelines.index', [
            'allowedComponentKeys' => $this->allowedUiComponentKeysForSurface('guideline.index'),
            'pageTitle' => 'Brand Guidelines',
            'activeNav' => 'guidelines',
            'breadcrumbs' => [['label' => 'Guidelines']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
