<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;

class ContributorAccountPageController extends Controller
{
    public function __construct(
        private readonly ContributorProfileRepository $contributorProfileRepository,
        private readonly ContractRepository           $contractRepository,
        private readonly GuidelinesRepository         $guidelinesRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();
        $contract = $this->contractRepository->latestForSite($siteId);

        return $this->view('open-collab.settings.index', [
            'profile' => $this->contributorProfileRepository->findByUserId($userId),
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key'),
            'contractSignatures' => $this->contractRepository->getForUser($userId, $contract->id)->toArray(),
            'guidelinesAck' => $this->guidelinesRepository->getForUser($userId, $siteId),
            'site' => SiteContext::slug()
        ]);
    }
}