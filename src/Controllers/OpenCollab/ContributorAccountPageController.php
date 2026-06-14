<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use App\Services\OpenCollab\UserConsentService;
use App\ViewModels\OpenCollab\ProfileStepViewModel;

class ContributorAccountPageController extends Controller
{
    public function __construct(
        private readonly ContributorProfileRepository         $contributorProfileRepository,
        private readonly ContractRepository                   $contractRepository,
        private readonly GuidelinesRepository                 $guidelinesRepository,
        private readonly UserConsentService                   $userConsentService,
        private readonly ContributorProfileFieldConfigService $profileFieldConfigService,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $userId = Auth::id();
        $siteId = SiteContext::getId();
        $site = Site::find($siteId);

        $contract = $this->contractRepository->latestForSite($siteId);
        $profile = $this->contributorProfileRepository->findByUserId($userId);

        $profileFields = $site
            ? $this->profileFieldConfigService->activeFieldsForSite($site)
            : collect([]);

        $profileStep = ProfileStepViewModel::fromFields($profileFields, $profile);

        return $this->view('open-collab.settings.index', [
            'profile' => $profile,
            'profileStep' => $profileStep,
            'stripePublicKey' => $_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key'),
            'contractSignatures' => !empty($contract)
                ? $this->contractRepository->getForUser($userId, $contract->id)?->toArray() ?? []
                : [],
            'guidelinesAck' => $this->guidelinesRepository->getForUser($userId, $siteId),
            'site' => SiteContext::slug(),
            'currentUser' => User::find($userId),
            'extraHead' => '<script src="/js/open-collab/contributor-avatar-settings.js" defer></script>',
        ]);
    }
}
