<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Models\Site;

class SiteController extends Controller
{

    public function index()
    {
        $sites = Site::all();
        return $this->jsonResponse($sites->toArray());
    }

    public function getContactInfo()
    {
        $site = SiteContext::get();


        return $this->jsonResponse($site->getContactInfo());
    }
}