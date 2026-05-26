<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabSiteUserRole;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\LegacyRoleToSiteRoleMapper;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Services\OpenCollab\SitePermissionResolver;
use App\Services\OpenCollab\SiteRoleAssignmentService;
use App\Tests\Unit\Repositories\RepositoryTestCase;
use Mockery;

class SiteRoleAssignmentServiceTest extends RepositoryTestCase
{
    private SiteRoleAssignmentService $service;
    private RbacBootstrapper $bootstrapper;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');
        $this->bootstrapper = new RbacBootstrapper(new RbacRepository());

        $resolver = Mockery::mock(SitePermissionResolver::class);
        $resolver->shouldReceive('invalidate')->byDefault();

        $this->service = new SiteRoleAssignmentService(
            new LegacyRoleToSiteRoleMapper(),
            $this->bootstrapper,
            $resolver,
        );
    }

    public function test_sync_legacy_role_replaces_existing_site_role_with_mapped_role(): void
    {
        $site = Site::create(['name' => 'RBAC Site', 'slug' => 'rbac-site-assign', 'is_default' => false]);
        $user = User::create([
            'name' => 'Role Test',
            'email' => 'role-test@example.com',
            'password' => 'secret',
            'role' => 'contributor',
        ]);

        $this->bootstrapper->ensureSeeded($site->id);

        $reviewerRole = OpenCollabRole::where('slug', 'reviewer')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'role_id' => $reviewerRole->id,
        ]);

        $mapped = $this->service->syncLegacyRole($user->id, $site->id, 'finance');

        $assignedRoles = OpenCollabSiteUserRole::where('site_id', $site->id)
            ->where('user_id', $user->id)
            ->get();

        $this->assertSame('finance', $mapped);
        $this->assertCount(1, $assignedRoles);
        $this->assertEquals(
            OpenCollabRole::where('slug', 'finance')->first()->id,
            $assignedRoles->first()->role_id
        );
    }

    public function test_sync_legacy_role_returns_null_for_unknown_role(): void
    {
        $site = Site::create(['name' => 'RBAC Site 2', 'slug' => 'rbac-site-assign-2', 'is_default' => false]);
        $user = User::create([
            'name' => 'Unknown Role',
            'email' => 'unknown-role@example.com',
            'password' => 'secret',
            'role' => 'user',
        ]);

        $mapped = $this->service->syncLegacyRole($user->id, $site->id, 'user');

        $this->assertNull($mapped);
        $this->assertFalse(OpenCollabSiteUserRole::where('site_id', $site->id)->where('user_id', $user->id)->exists());
    }
}
