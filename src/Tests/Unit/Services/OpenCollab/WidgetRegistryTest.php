<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\WidgetResponse;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\TestCase;

class WidgetRegistryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── register / has / get ──────────────────────────────────────────────

    public function test_has_returns_false_for_unregistered_key(): void
    {
        $registry = new WidgetRegistry();

        $this->assertFalse($registry->has('earnings'));
    }

    public function test_register_makes_widget_retrievable(): void
    {
        $registry = new WidgetRegistry();
        $widget   = $this->mockWidget('earnings');

        $registry->register($widget);

        $this->assertTrue($registry->has('earnings'));
        $this->assertSame($widget, $registry->get('earnings'));
    }

    public function test_get_throws_for_unregistered_key(): void
    {
        $registry = new WidgetRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Widget [unknown] is not registered.');

        $registry->get('unknown');
    }

    public function test_registering_same_key_twice_overwrites_previous(): void
    {
        $registry = new WidgetRegistry();
        $first    = $this->mockWidget('earnings');
        $second   = $this->mockWidget('earnings');

        $registry->register($first);
        $registry->register($second);

        $this->assertSame($second, $registry->get('earnings'));
    }

    // ── all ───────────────────────────────────────────────────────────────

    public function test_all_returns_empty_array_when_nothing_registered(): void
    {
        $registry = new WidgetRegistry();

        $this->assertSame([], $registry->all());
    }

    public function test_all_returns_every_registered_widget(): void
    {
        $registry = new WidgetRegistry();
        $a        = $this->mockWidget('earnings');
        $b        = $this->mockWidget('drafts');
        $c        = $this->mockWidget('activity');

        $registry->register($a);
        $registry->register($b);
        $registry->register($c);

        $this->assertCount(3, $registry->all());
        $this->assertContains($a, $registry->all());
        $this->assertContains($b, $registry->all());
        $this->assertContains($c, $registry->all());
    }

    // ── forUser ───────────────────────────────────────────────────────────

    public function test_for_user_returns_only_visible_widgets(): void
    {
        $user     = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $registry = new WidgetRegistry();

        $visible = $this->mockWidget('earnings');
        $visible->shouldReceive('visibleFor')->with($user)->andReturn(true);

        $hidden = $this->mockWidget('onboarding');
        $hidden->shouldReceive('visibleFor')->with($user)->andReturn(false);

        $registry->register($visible);
        $registry->register($hidden);

        $result = $registry->forUser($user);

        $this->assertCount(1, $result);
        $this->assertSame($visible, $result[0]);
    }

    public function test_for_user_returns_empty_when_all_widgets_hidden(): void
    {
        $user     = Mockery::mock(User::class)->makePartial();
        $user->id = 2;
        $registry = new WidgetRegistry();

        $w = $this->mockWidget('earnings');
        $w->shouldReceive('visibleFor')->with($user)->andReturn(false);

        $registry->register($w);

        $this->assertSame([], $registry->forUser($user));
    }

    // ── permissions ───────────────────────────────────────────────────────

    public function test_permissions_for_returns_empty_array_when_none_registered(): void
    {
        $registry = new WidgetRegistry();
        $registry->register($this->mockWidget('earnings'));

        $this->assertSame([], $registry->permissionsFor('earnings'));
    }

    public function test_permissions_for_returns_registered_permissions(): void
    {
        $registry    = new WidgetRegistry();
        $permissions = ['payout.view', 'payout.request'];

        $registry->register($this->mockWidget('earnings'), $permissions);

        $this->assertSame($permissions, $registry->permissionsFor('earnings'));
    }

    public function test_permissions_for_unregistered_key_returns_empty_array(): void
    {
        $registry = new WidgetRegistry();

        $this->assertSame([], $registry->permissionsFor('does_not_exist'));
    }

    public function test_register_component_makes_page_panel_retrievable_by_surface(): void
    {
        $registry = new WidgetRegistry();

        $registry->registerComponent([
            'key' => 'contributor.invitation',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Invitation',
            'capabilities' => ['contributor.invitation.view'],
            'component' => \App\Services\UI\Components\Contributor\ContributorInvitationPanel::class,
            'sort_order' => 20,
            'enabled' => true,
        ]);

        $components = $registry->componentsForSurface('contributor.show');

        $this->assertCount(1, $components);
        $this->assertSame('contributor.invitation', $components[0]['key']);
        $this->assertSame(['contributor.invitation.view'], $components[0]['capabilities']);
    }

    public function test_components_for_surface_are_sorted_by_sort_order_then_key(): void
    {
        $registry = new WidgetRegistry();

        $registry->registerComponent([
            'key' => 'b.action',
            'type' => 'page_action',
            'surface' => 'articles.index',
            'label' => 'B',
            'component' => 'BAction',
            'sort_order' => 20,
            'enabled' => true,
        ]);
        $registry->registerComponent([
            'key' => 'a.action',
            'type' => 'page_action',
            'surface' => 'articles.index',
            'label' => 'A',
            'component' => 'AAction',
            'sort_order' => 20,
            'enabled' => true,
        ]);
        $registry->registerComponent([
            'key' => 'c.action',
            'type' => 'page_action',
            'surface' => 'articles.index',
            'label' => 'C',
            'component' => 'CAction',
            'sort_order' => 30,
            'enabled' => true,
        ]);

        $components = $registry->componentsForSurface('articles.index');

        $this->assertSame(['a.action', 'b.action', 'c.action'], array_column($components, 'key'));
    }

    public function test_register_component_accepts_single_capability_key(): void
    {
        $registry = new WidgetRegistry();

        $registry->registerComponent([
            'key' => 'contributor.capabilities',
            'type' => 'page_panel',
            'surface' => 'contributor.show',
            'label' => 'Capabilities',
            'capability' => 'contributor.capabilities.view',
            'component' => \App\Services\UI\Components\Contributor\ContributorCapabilitiesPanel::class,
            'sort_order' => 30,
            'enabled' => true,
        ]);

        $components = $registry->componentsForSurface('contributor.show');

        $this->assertSame(['contributor.capabilities.view'], $components[0]['capabilities']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function mockWidget(string $key): DashboardWidgetInterface&\Mockery\MockInterface
    {
        $widget = Mockery::mock(DashboardWidgetInterface::class);
        $widget->shouldReceive('key')->andReturn($key);
        $widget->shouldReceive('title')->andReturn(ucfirst($key));
        return $widget;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// WidgetResponse
// ═══════════════════════════════════════════════════════════════════════════

class WidgetResponseTest extends TestCase
{
    // ── toArray shape ─────────────────────────────────────────────────────

    public function test_to_array_contains_all_required_keys(): void
    {
        $response = WidgetResponse::make('earnings', 'Earnings', ['total' => 100]);

        $array = $response->toArray();

        $this->assertArrayHasKey('key',   $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('data',  $array);
        $this->assertArrayHasKey('meta',  $array);
    }

    public function test_to_array_returns_correct_key_and_title(): void
    {
        $response = WidgetResponse::make('drafts', 'My Drafts', []);

        $array = $response->toArray();

        $this->assertSame('drafts',    $array['key']);
        $this->assertSame('My Drafts', $array['title']);
    }

    public function test_to_array_returns_data_unchanged(): void
    {
        $data     = ['total' => 42, 'currency' => 'GBP'];
        $response = WidgetResponse::make('earnings', 'Earnings', $data);

        $this->assertSame($data, $response->toArray()['data']);
    }

    public function test_to_array_with_empty_data(): void
    {
        $response = WidgetResponse::make('earnings', 'Earnings', []);

        $this->assertSame([], $response->toArray()['data']);
    }

    // ── meta ──────────────────────────────────────────────────────────────

    public function test_meta_always_contains_loaded_at(): void
    {
        $response = WidgetResponse::make('earnings', 'Earnings', []);

        $meta = $response->toArray()['meta'];

        $this->assertArrayHasKey('loaded_at', $meta);
    }

    public function test_loaded_at_is_valid_iso8601(): void
    {
        $response = WidgetResponse::make('earnings', 'Earnings', []);

        $loadedAt = $response->toArray()['meta']['loaded_at'];

        $this->assertNotFalse(
            \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $loadedAt),
            "loaded_at [{$loadedAt}] is not a valid ISO 8601 / ATOM date."
        );
    }

    public function test_extra_meta_is_merged_with_loaded_at(): void
    {
        $response = WidgetResponse::make('earnings', 'Earnings', [], ['source' => 'cache']);

        $meta = $response->toArray()['meta'];

        $this->assertSame('cache', $meta['source']);
        $this->assertArrayHasKey('loaded_at', $meta);
    }

    public function test_explicit_loaded_at_in_meta_is_overridden_by_auto_value(): void
    {
        // The constructor always regenerates loaded_at — a caller-supplied value
        // should be overwritten because loaded_at must reflect the actual response time.
        $response = WidgetResponse::make('earnings', 'Earnings', [], [
            'loaded_at' => '2000-01-01T00:00:00+00:00',
        ]);

        $loadedAt = $response->toArray()['meta']['loaded_at'];

        // The stored value should NOT be the stub date from the year 2000.
        $this->assertNotSame('2000-01-01T00:00:00+00:00', $loadedAt);
    }

    public function test_multiple_extra_meta_keys_all_appear(): void
    {
        $response = WidgetResponse::make('earnings', 'Earnings', [], [
            'source'  => 'live',
            'version' => 2,
        ]);

        $meta = $response->toArray()['meta'];

        $this->assertSame('live', $meta['source']);
        $this->assertSame(2,      $meta['version']);
    }
}
