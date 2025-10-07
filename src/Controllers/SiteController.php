<?php

namespace App\Controllers;

use App\Models\Site;

class SiteController extends Controller
{

    public function index()
    {
        $sites = Site::all();
        return $this->jsonResponse($sites->toArray());
    }
}