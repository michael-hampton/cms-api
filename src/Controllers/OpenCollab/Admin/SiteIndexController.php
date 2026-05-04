<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\SiteRepository;

class SiteIndexController extends Controller
{
    public function __construct(private readonly SiteRepository $repository)
    {

        parent::__construct();
    }

    public function index(): mixed
    {
        $sites = $this->repository->findAll();

        return $this->view('open-collab.admin.sites.index', [
            'pageTitle' => 'Sites',
            'activeNav' => 'sites',
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Sites'],
            ],
            'sites' => $sites,
            'site' => SiteContext::slug()
        ]);
    }
}