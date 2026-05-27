<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SiteSettingsControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_show_renders_rbac_tabs_current_overrides_and_in_place_requests(): void
    {
        $this->enableSiteRbac();

        $member = $this->createUser([
            'name' => 'Settings Member',
            'email' => 'settings-member@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);

        $this->grantSitePermission($member, 'content.submit', false);

        $response = $this->getForSite('/open-collab/admin/sites/settings');
        $content = $response->getContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Roles', $content);
        $this->assertStringContainsString('Permissions Matrix', $content);
        $this->assertStringContainsString('Overrides', $content);
        $this->assertStringContainsString('Audit Log', $content);
        $this->assertStringContainsString('content.submit · deny', $content);
        $this->assertStringContainsString('/open-collab/admin/rbac/role-permissions/${roleId}', $content);
        $this->assertStringContainsString('/open-collab/admin/rbac/overrides/${userId}', $content);
        $this->assertStringContainsString('}, {reload: false});', $content);
    }

    public function test_show_returns_403_for_user_without_site_settings_permissions(): void
    {
        $this->enableSiteRbac();

        $restrictedUser = $this->createUser([
            'name' => 'Restricted Settings User',
            'email' => 'restricted-settings@example.com',
            'role' => 'user',
        ]);
        $this->actingAs($restrictedUser);

        $response = $this->getForSite('/open-collab/admin/sites/settings');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
