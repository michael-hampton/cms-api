<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Cache\Cache;
use App\Framework\Support\Cache\Contracts\CacheInterface;
use App\Framework\Support\Config;
use App\Models\OpenCollabPermission;
use App\Models\OpenCollabRole;
use App\Models\OpenCollabRolePermission;
use App\Models\OpenCollabSiteUserPermission;
use App\Models\OpenCollabSiteUserRole;
use App\Models\User;
use App\Repositories\OpenCollab\RbacRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\LegacyRoleToSiteRoleMapper;
use App\Services\OpenCollab\PermissionCacheInvalidator;
use App\Services\OpenCollab\RbacBootstrapper;
use App\Services\OpenCollab\SitePermissionBundleBuilder;
use App\Services\OpenCollab\SitePermissionResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SitePermissionResolverTest extends FunctionalTestCase
{
    use CreatesTestData;

    /** Resolver persistence only — no HTTP auth. */
    protected bool $authenticateDefaultUser = false;

    private SitePermissionResolver $resolver;
    private User $user;
    private UserSiteRepository $userSiteRepository;
    private RbacRepository $rbacRepository;
    private RbacBootstrapper $bootstrapper;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('rbac', require __DIR__ . '/../../../../config/rbac.php');

        $this->user = $this->createUser(['role' => 'contributor', 'site_id' => $this->siteId]);
        $this->userSiteRepository = new UserSiteRepository();
        $this->rbacRepository = new RbacRepository();
        $this->bootstrapper = new RbacBootstrapper($this->rbacRepository);
        $mapper = new LegacyRoleToSiteRoleMapper();
        $this->cache = new class implements CacheInterface {
            private array $items = [];

            public function get(string $key): mixed
            {
                return $this->items[$key] ?? null;
            }

            public function put(string $key, mixed $value, int $ttlSeconds): void
            {
                $this->items[$key] = $value;
            }

            public function forget(string $key): void
            {
                unset($this->items[$key]);
            }

            public function forgetMany(array $keys): void
            {
                foreach ($keys as $key) {
                    unset($this->items[$key]);
                }
            }

            public function flush(): void
            {
                $this->items = [];
            }

            public function has(string $key): bool
            {
                return array_key_exists($key, $this->items);
            }

            public function remember(string $key, int $ttlSeconds, callable $callback): mixed
            {
                if ($this->has($key)) {
                    return $this->items[$key];
                }

                return $this->items[$key] = $callback();
            }
        };

        $this->resolver = new SitePermissionResolver(
            $this->rbacRepository,
            new SitePermissionBundleBuilder($this->rbacRepository, $mapper, $this->bootstrapper),
            new PermissionCacheInvalidator($this->cache),
            $this->cache,
        );
    }

    public function test_resolves_legacy_permissions_when_feature_flag_disabled(): void
    {
        Config::set('rbac.site_enabled', false);

        $permissions = $this->resolver->forUser($this->user->id, $this->siteId);

        $this->assertContains('content.create', $permissions);
        $this->assertContains('contract.sign', $permissions);
    }

    public function test_merges_site_role_permissions_and_overrides_when_feature_flag_enabled(): void
    {
        Config::set('rbac.site_enabled', true);
        $this->bootstrapper->ensureSeeded($this->siteId);

        $reviewerRole = OpenCollabRole::where('slug', 'reviewer')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $this->user->id,
            'role_id' => $reviewerRole->id,
        ]);

        $approvePermission = OpenCollabPermission::where('slug', 'content.approve')->first();
        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $this->user->id,
            'permission_id' => $approvePermission->id,
            'granted' => false,
        ]);

        $this->resolver->invalidate($this->user->id, $this->siteId);

        $permissions = $this->resolver->forUser($this->user->id, $this->siteId);

        $this->assertContains('content.review', $permissions);
        $this->assertNotContains('content.approve', $permissions);
        $this->assertContains('content.create', $permissions);
    }

    public function test_enforces_site_isolation(): void
    {
        Config::set('rbac.site_enabled', true);

        $this->assertSame([], $this->resolver->forUser($this->user->id, $this->siteId + 999));
    }

    public function test_supports_direct_grants_without_any_role_assignment(): void
    {
        Config::set('rbac.site_enabled', true);
        $this->bootstrapper->ensureSeeded($this->siteId);
        $user = $this->createUser(['role' => 'user', 'site_id' => $this->siteId]);

        $permission = OpenCollabPermission::where('slug', 'creator.invite')->first();
        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => true,
        ]);

        $permissions = $this->resolver->forUser($user->id, $this->siteId);

        $this->assertContains('creator.invite', $permissions);
    }

    public function test_denied_override_removes_permission_from_site_role_assignment(): void
    {
        Config::set('rbac.site_enabled', true);
        $this->bootstrapper->ensureSeeded($this->siteId);
        $user = $this->createUser(['role' => 'user', 'site_id' => $this->siteId]);

        $brandManagerRole = OpenCollabRole::where('slug', 'brand_manager')->first();
        OpenCollabSiteUserRole::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'role_id' => $brandManagerRole->id,
        ]);

        $permission = OpenCollabPermission::where('slug', 'creator.invite')->first();
        OpenCollabSiteUserPermission::create([
            'site_id' => $this->siteId,
            'user_id' => $user->id,
            'permission_id' => $permission->id,
            'granted' => false,
        ]);

        $this->resolver->invalidate($user->id, $this->siteId);

        $this->assertFalse($this->resolver->allows($user->id, $this->siteId, 'creator.invite'));
    }

    public function test_bundle_includes_global_admin_flag(): void
    {
        $admin = $this->createUser(['role' => 'admin', 'site_id' => $this->siteId]);

        $bundle = $this->resolver->bundleForUser($admin->id);

        $this->assertTrue($bundle['is_global_admin']);
        $this->assertSame($admin->id, $bundle['user_id']);
        $this->assertNotEmpty($bundle['assignments']);
    }
}
