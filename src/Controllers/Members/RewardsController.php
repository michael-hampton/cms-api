<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Rewards\RewardsService;

class RewardsController extends Controller
{
    public function __construct(
        private readonly RewardsService    $rewardsService,
        private readonly RewardsRepository $rewardsRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        return $this->view('member/rewards/index', [
            'member' => MemberAuth::getMember(),
            'site' => SiteContext::get(),
        ]);
    }
}