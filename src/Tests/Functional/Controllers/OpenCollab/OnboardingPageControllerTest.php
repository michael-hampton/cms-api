<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OnboardingPageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_show_returns_403_without_onboarding_permission(): void
    {
        $this->enableSiteRbac();

        $contributor = $this->createUser([
            'email' => 'onboarding-page-restricted@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/onboarding');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_show_returns_200_when_onboarding_permission_is_granted(): void
    {
        $this->enableSiteRbac();

        $contributor = $this->createUser([
            'email' => 'onboarding-page-allowed@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->grantSitePermission($contributor, 'onboarding.view');
        $this->actingAs($contributor);

        $response = $this->getForSite('/open-collab/onboarding');

        $this->assertEquals(200, $response->getStatusCode());
    }
}
