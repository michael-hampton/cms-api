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
        $site = Mockery::mock(Site::class)->makePartial();
        $site->id = 1;
        $site->slug = 'test-site';
        $site->name = 'Test Site';
        SiteContext::set($site);

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

        $this->registry->registerComponent([
            'key' => 'contributor.invitation',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Invitation',
            'capabilities' => ['contributor.invitation.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorInvitationPanel::class,
            'sort_order' => 20,
            'enabled' => true,
        ]);
        $this->registry->registerComponent([
            'key' => 'contributor.details',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Details',
            'capabilities' => ['contributor.details.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorDetailsPanel::class,
            'sort_order' => 10,
            'enabled' => true,
        ]);
        $this->registry->registerComponent([
            'key' => 'contributor.disabled_panel',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Disabled',
            'capabilities' => ['contributor.activity.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorCapabilitiesPanel::class,
            'sort_order' => 30,
            'enabled' => false,
        ]);

        $this->resolver = new WidgetResolver(
            $this->registry,
            $this->settingsRepository,
            $this->permissionResolver,
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

        $widgets = $this->resolver->resolveForUser($this->makeUser(5));

        $this->assertSame(['drafts', 'earnings', 'activity', 'quick_links'], array_map(fn($widget) => $widget->key(), $widgets));
    }

    public function test_resolves_reviewer_widgets_from_permissions(): void
    {
        $this->permissionResolver->shouldReceive('forUser')->with(7, 1)->andReturn(['content.review', 'content.approve']);
        $this->permissionResolver->shouldReceive('allows')->with(7, 1, 'onboarding.view')->andReturn(false);
        $this->settingsRepository->shouldReceive('getForUser')->with(7)->andReturn(new Collection([]));

        $widgets = $this->resolver->resolveForUser($this->makeUser(7));

        $this->assertSame(['review_queue', 'approvals', 'activity'], array_map(fn($widget) => $widget->key(), $widgets));
    }

    public function test_resolves_finance_widgets_from_permissions(): void
    {
        $this->permissionResolver->shouldReceive('forUser')->with(9, 1)->andReturn(['payout.view']);
        $this->permissionResolver->shouldReceive('allows')->with(9, 1, 'onboarding.view')->andReturn(false);
        $this->settingsRepository->shouldReceive('getForUser')->with(9)->andReturn(new Collection([]));

        $widgets = $this->resolver->resolveForUser($this->makeUser(9));

        $this->assertSame(['activity', 'quick_links'], array_map(fn($widget) => $widget->key(), $widgets));
    }

    public function test_returns_default_widgets_when_no_permission_set_matches(): void
    {
        $this->permissionResolver->shouldReceive('forUser')->with(11, 1)->andReturn([]);
        $this->permissionResolver->shouldReceive('allows')->with(11, 1, 'onboarding.view')->andReturn(false);
        $this->settingsRepository->shouldReceive('getForUser')->with(11)->andReturn(new Collection([]));

        $widgets = $this->resolver->resolveForUser($this->makeUser(11));

        $this->assertSame(['drafts', 'earnings', 'activity', 'quick_links'], array_map(fn($widget) => $widget->key(), $widgets));
    }

    public function test_available_for_user_respects_saved_positions_and_enabled_flags(): void
    {
        $this->permissionResolver->shouldReceive('allows')->with(13, 1, 'onboarding.view')->andReturn(false);
        $this->permissionResolver->shouldReceive('forUser')->with(13, 1)->andReturn(['content.create', 'payout.request']);
        $this->settingsRepository->shouldReceive('getForUser')->with(13)->andReturn(new Collection([
            ['widget_key' => 'earnings', 'enabled' => true, 'position' => 0],
            ['widget_key' => 'drafts', 'enabled' => false, 'position' => 6],
        ]));

        $widgets = $this->resolver->availableForUser($this->makeUser(13));

        $this->assertSame([
            ['key' => 'earnings', 'enabled' => true, 'position' => 0],
            ['key' => 'activity', 'enabled' => true, 'position' => 3],
            ['key' => 'quick_links', 'enabled' => true, 'position' => 4],
            ['key' => 'drafts', 'enabled' => false, 'position' => 6],
        ], array_map(fn($widget) => [
            'key' => $widget['key'],
            'enabled' => $widget['enabled'],
            'position' => $widget['position'],
        ], $widgets));
    }

    public function test_available_for_user_returns_onboarding_manifest_when_onboarding_gate_is_active(): void
    {
        $registry = new WidgetRegistry();
        foreach (['onboarding', 'drafts', 'earnings', 'activity', 'quick_links'] as $key) {
            $widget = Mockery::mock(DashboardWidgetInterface::class);
            $widget->shouldReceive('key')->andReturn($key);
            $widget->shouldReceive('title')->andReturn(ucfirst($key));
            $widget->shouldReceive('visibleFor')->andReturn($key === 'onboarding');
            $registry->register($widget, config("dashboard.widget_permissions.{$key}", []));
        }

        $resolver = new WidgetResolver(
            $registry,
            $this->settingsRepository,
            $this->permissionResolver,
        );

        $this->permissionResolver->shouldReceive('allows')->with(15, 1, 'onboarding.view')->andReturn(true);
        $this->settingsRepository->shouldReceive('getForUser')->never();

        $widgets = $resolver->availableForUser($this->makeUser(15));

        $this->assertSame([[
            'key' => 'onboarding',
            'title' => 'Onboarding',
            'enabled' => true,
            'position' => 0,
        ]], $widgets);
    }

    public function test_allowed_for_surface_returns_dashboard_widgets_in_resolved_order(): void
    {
        $this->permissionResolver->shouldReceive('forUser')->with(21, 1)->andReturn(['content.create', 'payout.request']);
        $this->permissionResolver->shouldReceive('allows')->with(21, 1, 'onboarding.view')->andReturn(false);
        $this->settingsRepository->shouldReceive('getForUser')->with(21)->andReturn(new Collection([]));

        $components = $this->resolver->allowedForSurface(21, 1, 'dashboard.index');

        $this->assertSame(
            ['drafts', 'earnings', 'activity', 'quick_links'],
            array_column($components, 'key')
        );
    }

    public function test_allowed_for_surface_returns_page_panels_for_non_dashboard_surface(): void
    {
        $this->permissionResolver->shouldReceive('allows')->with(22, 1, 'contributor.invitation.view')->andReturn(true);
        $this->permissionResolver->shouldReceive('allows')->with(22, 1, 'contributor.details.view')->andReturn(true);

        $components = $this->resolver->allowedForSurface(22, 1, 'contributor.show');

        $this->assertSame(
            ['contributor.details', 'contributor.invitation'],
            array_column($components, 'key')
        );
    }

    public function test_allowed_for_surface_excludes_disabled_components(): void
    {
        $this->permissionResolver->shouldReceive('allows')->with(23, 1, 'contributor.invitation.view')->andReturn(true);
        $this->permissionResolver->shouldReceive('allows')->with(23, 1, 'contributor.details.view')->andReturn(true);

        $components = $this->resolver->allowedForSurface(23, 1, 'contributor.show');

        $this->assertNotContains('contributor.disabled_panel', array_column($components, 'key'));
    }

    public function test_allowed_for_surface_returns_empty_array_for_unknown_surface(): void
    {
        $this->assertSame([], $this->resolver->allowedForSurface(24, 1, 'missing.surface'));
    }

    private function makeUser(int $id): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        return $user;
    }
}
