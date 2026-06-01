<?php

namespace App\Tests\Unit\Jobs\OpenCollab;

use App\Framework\Support\Config;
use App\Jobs\OpenCollab\MigrateLegacyUserRolesToSiteRolesJob;
use App\Models\OpenCollabRbacAuditLog;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabSiteUserRole;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\LegacyRoleToSiteRoleMapper;
use App\Services\OpenCollab\RbacAuditLogger;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Services\OpenCollab\SitePermissionResolver;
use App\Services\OpenCollab\SiteRoleAssignmentService;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class MigrateLegacyUserRolesToSiteRolesJobTest extends RepositoryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');
    }

    public function test_it_backfills_site_roles_for_legacy_users_idempotently(): void
    {
        $site = Site::create(['name' => 'Job Site', 'slug' => 'job-site-rbac', 'is_default' => false]);
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-rbac-job@example.com',
            'password' => 'secret',
            'role' => 'admin',
        ]);

        UserSite::create(['user_id' => $user->id, 'site_id' => $site->id]);

        $job = $this->makeJob();
        $job->handle();
        $job->handle();

        $financeRole = OpenCollabRole::where('slug', 'site_admin')->first();

        $this->assertEquals(1, OpenCollabSiteUserRole::where([
            'site_id' => $site->id,
            'user_id' => $user->id,
            'role_id' => $financeRole->id,
        ])->count());

        $this->assertGreaterThanOrEqual(1, OpenCollabRbacAuditLog::where('action', 'legacy_role_migrated')->count());
    }

    public function test_it_logs_skipped_users_without_site_membership(): void
    {
        $user = User::create([
            'name' => 'No Site User',
            'email' => 'no-site-rbac-job@example.com',
            'password' => 'secret',
            'role' => 'admin',
        ]);

        $this->makeJob()->handle();

        $this->assertDatabaseHas('oc_rbac_audit_logs', [
            'target_user_id' => $user->id,
            'action' => 'legacy_role_migration_skipped',
        ]);
    }

    private function makeJob(): MigrateLegacyUserRolesToSiteRolesJob
    {
        $userSiteRepository = new UserSiteRepository();
        $rbacRepository = new RbacRepository();
        $bootstrapper = new RbacBootstrapper($rbacRepository);
        $resolver = new SitePermissionResolver(
            $userSiteRepository,
            $rbacRepository,
            new LegacyRoleToSiteRoleMapper(),
            $bootstrapper,
        );

        return new MigrateLegacyUserRolesToSiteRolesJob(
            $userSiteRepository,
            new SiteRoleAssignmentService(
                new LegacyRoleToSiteRoleMapper(),
                $bootstrapper,
                $resolver,
            ),
            new RbacAuditLogger($rbacRepository),
        );
    }
}
