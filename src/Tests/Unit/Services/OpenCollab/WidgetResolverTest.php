<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Config;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\WidgetSettingsRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\WidgetResolver;
use App\Services\OpenCollab\LegacyRoleToSiteRoleMapper;
use App\Services\OpenCollab\SitePermissionResolver;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class WidgetResolverTest extends TestCase
{
    private WidgetRegistry $registry;
    private MockInterface $settingsRepository;
    private SitePermissionResolver $permissionResolver;
    private WidgetResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('dashboard', require __DIR__ . '/../../../../config/dashboard.php');
        SiteContext::set(new Site(['id' => 1, 'slug' => 'test-site', 'name' => 'Test Site']));

        $this->registry = new WidgetRegistry();
        $this->settingsRepository = Mockery::mock(WidgetSettingsRepository::class);
        $this->permissionResolver = Mockery::mock(SitePermissionResolver::class);

        foreach (['onboarding', 'drafts', 'earnings', 'review_queue', 'approvals', 'activity', 'quick_links'] as $key) {
            $widget = Mockery::mock(DashboardWidgetInterface::class);
            $widget->shouldReceive('key')->andReturn($key);
            $widget->shouldReceive('title')->andReturn(ucfirst($key));
            $widget->shouldReceive('visibleFor')->andReturn($key === 'onboarding' ? false : true);
            $this->registry->register($widget, config("dashboard.widget_permissions.{$key}", []));
        }

        $this->resolver = new WidgetResolver(
            $this->registry,
            $this->settingsRepository,
            $this->permissionResolver,
            new LegacyRoleToSiteRoleMapper(),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolves_creator_widgets_from_permissions(): void
    {
        $this->permissionResolver->shouldReceive('forUser')->with(5, 1)->andReturn(['content.create', 'payout.request']);
        $this->permissionResolver->shouldReceive('allows')->with(5, 1, 'onboarding.view')->andReturn(false);
        $this->settingsRepository->shouldReceive('getForUser')->with(5)->andReturn(new Collection([]));

        $widgets = $this->resolver->resolveForUser(new User(['id' => 5, 'role' => 'contributor']));

        $this->assertSame(['drafts', 'earnings', 'activity', 'quick_links'], array_map(fn($widget) => $widget->key(), $widgets));
    }

    public function test_resolves_reviewer_widgets_from_permissions(): void
    {
        $this->permissionResolver->shouldReceive('forUser')->with(7, 1)->andReturn(['content.review', 'content.approve']);
        $this->permissionResolver->shouldReceive('allows')->with(7, 1, 'onboarding.view')->andReturn(false);
        $this->settingsRepository->shouldReceive('getForUser')->with(7)->andReturn(new Collection([]));

        $widgets = $this->resolver->resolveForUser(new User(['id' => 7, 'role' => 'editor']));

        $this->assertSame(['review_queue', 'approvals', 'activity'], array_map(fn($widget) => $widget->key(), $widgets));
    }
}
