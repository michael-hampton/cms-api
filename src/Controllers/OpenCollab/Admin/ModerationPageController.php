<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;

class ModerationPageController extends Controller
{
    public function show(int $id)
    {
        return $this->view('open-collab/admin/moderation/show', [
            'site' => SiteContext::slug(),
            'queueEntryId' => $id,
        ]);
    }

}