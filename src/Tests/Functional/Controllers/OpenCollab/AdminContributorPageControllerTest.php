<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminContributorPageControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_show_renders_capability_api_endpoints_with_site_slug(): void
    {
        $contributor = $this->createUser([
            'name' => 'Capability Contributor',
            'email' => 'capability-contributor@example.com',
            'role' => 'contributor',
            'is_contributor' => true,
            'is_active' => true,
        ]);

        $response = $this->getForSite("/open-collab/admin/contributors/{$contributor->id}");
        $content = $response->getContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString("return `/api/\${this._site}/open-collab/admin`;", $content);
        $this->assertStringContainsString('capabilityList: (cid) => `${this._contributor(cid)}/capabilities`', $content);
        $this->assertStringContainsString('capabilityGrant: (cid, key) => `${this._capability(cid, key)}/grant`', $content);
        $this->assertStringContainsString('capabilityRevoke: (cid, key) => `${this._capability(cid, key)}/revoke`', $content);
        $this->assertStringContainsString('capabilityReset: (cid, key) => `${this._capability(cid, key)}/override`', $content);
        $this->assertStringContainsString("site: '{$this->siteSlug}'", $content);
    }
}
