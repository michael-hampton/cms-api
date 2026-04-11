<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;

class ContributorLoginPageController extends Controller
{
    public function login()
    {
        return $this->view('open-collab.contributor-login', ['site' => SiteContext::slug()]);
    }
}