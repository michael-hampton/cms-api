<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\CreatorBalanceService;

class AdminPayoutController extends Controller
{
    public function __construct(private readonly CreatorBalanceService $creatorBalanceService)
    {
        parent::__construct();

    }
    public function stats()
    {
        return $this->resourceResponse($this->creatorBalanceService->balances(Auth::id(), SiteContext::getId()));
    }
}