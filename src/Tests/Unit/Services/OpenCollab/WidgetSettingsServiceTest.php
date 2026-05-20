<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\User;
use App\Repositories\OpenCollab\WidgetSettingsRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\WidgetSettingsService;
use PHPUnit\Framework\TestCase;

class WidgetSettingsServiceTest extends TestCase
{
    // ── saveWidgetConfig ──────────────────────────────────────────────────────

    public function test_it_saves_widget_config_for_a_registered_key(): void
    {
        $registry = $this->registryWith(['earnings']);
        $repo     = $this->createMock(WidgetSettingsRepository::class);

        $repo->expects($this->once())
            ->method('saveWidgetConfig')
            ->with(42, 'earnings', true, 0, []);

        $service = new WidgetSettingsService($repo, $registry);
        $service->saveWidgetConfig(42, 'earnings', true, 0);
    }

    public function test_it_throws_when_saving_config_for_unregistered_key(): void
    {
        $registry = $this->registryWith([]);
        $repo     = $this->createMock(WidgetSettingsRepository::class);
        $repo->expects($this->never())->method('saveWidgetConfig');

        $service = new WidgetSettingsService($repo, $registry);

        $this->expectException(\InvalidArgumentException::class);
        $service->saveWidgetConfig(1, 'unknown_widget', true, 0);
    }

    public function test_it_throws_when_position_is_negative(): void
    {
        $registry = $this->registryWith(['earnings']);
        $repo     = $this->createMock(WidgetSettingsRepository::class);
        $repo->expects($this->never())->method('saveWidgetConfig');

        $service = new WidgetSettingsService($repo, $registry);

        $this->expectException(\InvalidArgumentException::class);
        $service->saveWidgetConfig(1, 'earnings', true, -1);
    }

    public function test_it_passes_widget_specific_settings_through(): void
    {
        $registry = $this->registryWith(['earnings']);
        $repo     = $this->createMock(WidgetSettingsRepository::class);

        $settings = ['collapsed' => true, 'theme' => 'compact'];

        $repo->expects($this->once())
            ->method('saveWidgetConfig')
            ->with(7, 'earnings', false, 2, $settings);

        $service = new WidgetSettingsService($repo, $registry);
        $service->saveWidgetConfig(7, 'earnings', false, 2, $settings);
    }

    // ── updatePositions ───────────────────────────────────────────────────────

    public function test_it_updates_positions_for_valid_keys(): void
    {
        $registry = $this->registryWith(['earnings', 'drafts', 'activity']);
        $repo     = $this->createMock(WidgetSettingsRepository::class);

        $positions = [
            ['widget_key' => 'activity', 'position' => 0],
            ['widget_key' => 'earnings', 'position' => 1],
            ['widget_key' => 'drafts',   'position' => 2],
        ];

        $repo->expects($this->once())
            ->method('updatePositions')
            ->with(5, collect($positions));

        $service = new WidgetSettingsService($repo, $registry);
        $service->updatePositions(5, $positions);
    }

    public function test_it_throws_before_writing_if_any_key_is_unregistered(): void
    {
        $registry = $this->registryWith(['earnings']);
        $repo     = $this->createMock(WidgetSettingsRepository::class);
        $repo->expects($this->never())->method('updatePositions');

        $service = new WidgetSettingsService($repo, $registry);

        $this->expectException(\InvalidArgumentException::class);

        $service->updatePositions(1, [
            ['widget_key' => 'earnings',       'position' => 0],
            ['widget_key' => 'does_not_exist', 'position' => 1],
        ]);
    }

    public function test_it_throws_before_writing_if_any_position_is_invalid(): void
    {
        $registry = $this->registryWith(['earnings', 'drafts']);
        $repo     = $this->createMock(WidgetSettingsRepository::class);
        $repo->expects($this->never())->method('updatePositions');

        $service = new WidgetSettingsService($repo, $registry);

        $this->expectException(\InvalidArgumentException::class);

        $service->updatePositions(1, [
            ['widget_key' => 'earnings', 'position' => 0],
            ['widget_key' => 'drafts',   'position' => -1],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function registryWith(array $keys): WidgetRegistry
    {
        $registry = new WidgetRegistry();

        foreach ($keys as $key) {
            $widget = $this->createMock(DashboardWidgetInterface::class);
            $widget->method('key')->willReturn($key);
            $widget->method('title')->willReturn(ucfirst($key));
            $widget->method('visibleFor')->willReturn(true);
            $widget->method('data')->willReturn([]);
            $registry->register($widget);
        }

        return $registry;
    }
}