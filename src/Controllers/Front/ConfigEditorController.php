<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;

class ConfigEditorController extends Controller
{

    public function show()
    {
        return $this->view('public-content-v2/config-editor', ['siteId' => SiteContext::getId()]);
    }
}