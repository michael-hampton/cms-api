<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Framework\Http\Response;

/**
 * Renders the Open Collab admin escalation queue.
 *
 * Route:
 *   GET /{site}/open-collab/admin/escalations
 */
class AdminEscalationPageController extends Controller
{
    public function index(): Response
    {
        return $this->view('open-collab.admin.escalations.index', [
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
