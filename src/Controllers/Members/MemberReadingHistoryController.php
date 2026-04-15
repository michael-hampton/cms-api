<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;

class MemberReadingHistoryController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        return $this->view('member/reading-history', [
            'site' => SiteContext::get(),
            'member' => MemberAuth::getMember()
        ]);
    }
}