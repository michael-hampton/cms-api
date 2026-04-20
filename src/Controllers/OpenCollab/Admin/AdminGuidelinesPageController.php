<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;

/**
 * GET /admin/guidelines
 */
class AdminGuidelinesPageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('open-collab.admin.guidelines.index', [
            'pageTitle' => 'Brand Guidelines',
            'activeNav' => 'guidelines',
            'breadcrumbs' => [['label' => 'Guidelines']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}