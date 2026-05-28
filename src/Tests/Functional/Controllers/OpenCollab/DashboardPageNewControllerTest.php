<?php

namespace App\Tests\Functional\Controllers\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabSiteUserRole;
use App\Models\User;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class DashboardPageNewControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');
        Config::set('rbac.site_enabled', true);
        (new RbacBootstrapper(new RbacRepository()))->ensureSeeded($this->siteId);

        $this->user = $this->createUser([
            'email' => 'dashboard-rbac@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($this->user);
    }

    public function test_widget_endpoint_returns_forbidden_without_required_permission(): void
    {
        $response = $this->getForSite('/api/open-collab/dashboard/widgets/review_queue');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_widget_endpoint_returns_widget_data_when_permission_is_present(): void
    {
        $user = $this->createUser([
            'email' => 'dashboard-reviewer@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($user);

        $role = OpenCollabRole::where('slug', 'reviewer')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $response = $this->getForSite('/api/open-collab/dashboard/widgets/review_queue');
        $payload = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('review_queue', $payload['key']);
        $this->assertArrayHasKey('data', $payload);
    }

    public function test_widget_index_keeps_legacy_dashboard_manifest_shape(): void
    {
        $user = $this->createUser([
            'email' => 'dashboard-creator@example.com',
            'role' => 'user',
            'is_contributor' => true,
        ]);
        $this->actingAs($user);

        $role = OpenCollabRole::where('slug', 'creator')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $role->id,
        ]);

        $response = $this->getForSite('/api/open-collab/dashboard/widgets');
        $payload = json_decode($response->getContent(), true);
        $widgets = $payload['data']['widgets'] ?? [];

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($widgets);
        $this->assertArrayHasKey('key', $widgets[0]);
        $this->assertArrayHasKey('title', $widgets[0]);
        $this->assertArrayHasKey('enabled', $widgets[0]);
        $this->assertArrayHasKey('position', $widgets[0]);
    }
}
