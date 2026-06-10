<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use App\ViewModels\OpenCollab\ContributorRequestFormViewModel;

/**
 * GET /{site}/open-collab/request-access
 *
 * Renders the public self-service contributor registration form.
 *
 * Ticket 3: loads active dynamic field definitions for the contributor_request
 * context and builds a ProfileStepViewModel so the view can render additional
 * fields using the same profile-field.php partial as onboarding/settings.
 *
 * The existing name / email / bio fields are rendered by the template directly
 * (they are not database-backed field definitions). Dynamic fields are rendered
 * after the core fields in an "Additional information" section if any are configured.
 */
class ContributorRequestPageController extends Controller
{
    public function __construct(
        private readonly ContributorProfileFieldConfigService $profileFieldConfigService,
    )
    {
        parent::__construct();
    }

    public function show()
    {
        $site = Site::find(SiteContext::getId());
        $siteSlug = SiteContext::slug();

        $requestForm = $site
            ? ContributorRequestFormViewModel::fromFields(
                $this->profileFieldConfigService->activeRequestFieldsForSite($site)
            )
            : new ContributorRequestFormViewModel([]);

        return $this->view('open-collab.contributor-request', [
            'site' => $siteSlug,
            'requiresApproval' => (bool)($site?->require_invite_approval ?? true),
            'submitted' => false,
            'requestForm' => $requestForm,
        ]);
    }
}