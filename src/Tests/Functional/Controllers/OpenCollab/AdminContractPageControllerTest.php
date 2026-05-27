<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminContractPageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_index_returns_200_for_admin_user(): void
    {
        $response = $this->getForSite('/open-collab/admin/contracts');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_index_returns_403_for_user_without_contract_permissions(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'email' => 'admin-contract-page-restricted@example.com',
            'role' => 'user',
        ]);
        $this->grantSitePermission($restrictedUser, 'site.members');
        $this->actingAs($restrictedUser);

        $response = $this->getForSite('/open-collab/admin/contracts');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
