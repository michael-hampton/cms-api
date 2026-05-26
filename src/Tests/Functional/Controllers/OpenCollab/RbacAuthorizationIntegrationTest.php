<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\Contract;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabRole;
use App\Models\User;
use App\Models\UserSite;
use App\Models\OpenCollabSiteUserRole;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RbacAuthorizationIntegrationTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSiteExists();
        Config::set('rbac.site_enabled', true);
        (new RbacBootstrapper())->ensureSeeded($this->siteId);
    }

    public function test_editor_cannot_publish_contract_without_contract_publish_permission(): void
    {
        $reviewer = $this->createUser([
            'email' => 'reviewer-rbac@example.com',
            'role' => 'user',
            'is_contributor' => false,
        ]);
        $this->actingAs($reviewer);

        $reviewerRole = OpenCollabRole::where('slug', 'reviewer')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $reviewer->id,
            'role_id' => $reviewerRole->id,
        ]);

        $contract = Contract::create([
            'site_id' => $this->siteId,
            'version' => 1,
            'content' => str_repeat('contract ', 10),
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postForSite("/api/open-collab/admin/contracts/{$contract->id}/publish");

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_contributor_cannot_submit_article_when_submit_permission_is_denied_by_override(): void
    {
        $contributor = $this->createUser([
            'email' => 'submit-denied@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($contributor);

        $page = $this->createPage([
            'contributor_id' => $contributor->id,
            'status' => 'draft',
        ]);

        $permission = OpenCollabPermission::where('slug', 'content.submit')->first();
        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $contributor->id,
            'permission_id' => $permission->id,
            'granted' => false,
        ]);

        $response = $this->postForSite("/api/open-collab/pages/{$page->id}/submit");

        $this->assertEquals(403, $response->getStatusCode());
    }
}
