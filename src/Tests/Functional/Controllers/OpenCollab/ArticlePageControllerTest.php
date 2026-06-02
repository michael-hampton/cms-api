<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Models\ContributorProfile;
use App\Models\ContributorOnboardingStep;
use App\Models\Site;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ArticlePageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_create_returns_403_without_content_create_permission(): void
    {
        $this->enableSiteRbac();
        $this->requireOnlyProfileOnboarding();

        $contributor = $this->createUser([
            'email' => 'article-page-restricted@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->completeProfileOnboarding($contributor);
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/articles/create');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_create_returns_200_when_content_create_permission_is_granted(): void
    {
        $this->enableSiteRbac();
        $this->requireOnlyProfileOnboarding();

        $contributor = $this->createUser([
            'email' => 'article-page-allowed@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->grantSitePermission($contributor, 'content.create');
        $this->completeProfileOnboarding($contributor);
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/articles/create');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_create_returns_redirect_when_onboarding_is_incomplete(): void
    {
        $this->enableSiteRbac();
        $this->requireOnlyProfileOnboarding();

        $contributor = $this->createUser([
            'email' => 'article-page-onboarding-incomplete@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->grantSitePermission($contributor, 'content.create');
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/articles/create');

        $this->assertEquals(302, $response->getStatusCode());
    }

    private function requireOnlyProfileOnboarding(): void
    {
        $this->ensureSiteExists();

        Site::find($this->siteId)->update([
            'require_payment_setup' => false,
            'require_contracts' => false,
            'require_guidelines_ack' => false,
            'require_age_verification' => false,
        ]);
    }

    private function completeProfileOnboarding(User $contributor): void
    {
        ContributorProfile::create([
            'user_id' => $contributor->id,
            'bio' => 'A contributor biography long enough to pass onboarding.',
        ]);

        ContributorOnboardingStep::create([
            'user_id' => $contributor->id,
            'site_id' => $this->siteId,
            'step' => 'profile',
            'status' => OnboardingStepStatus::Completed->value,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
