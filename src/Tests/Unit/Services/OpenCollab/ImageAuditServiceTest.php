<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Framework\Support\Logger;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\OpenCollab\ImageAuditService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ImageAuditServiceTest extends TestCase
{
    private Mockery\MockInterface $activityRepository;
    private Mockery\MockInterface $logger;
    private ImageAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = Mockery::mock(ActivityRepository::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->service = new ImageAuditService($this->activityRepository, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_record_attach_delegates_to_activity_repository(): void
    {
        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                5,
                ActivityEventType::ArticleUpdated,
                Mockery::on(fn(array $p): bool =>
                    $p['page_id'] === 10
                    && $p['block_id'] === 'block_1'
                    && $p['cms_image_id'] === 42
                    && $p['action'] === 'image_attached'
                ),
            );

        $this->logger->shouldNotReceive('warning');

        $this->service->recordAttach(1, 5, 10, 'block_1', 42);
        $this->assertTrue(true);
    }

    public function test_record_logs_a_warning_and_does_not_throw_when_the_repository_fails(): void
    {
        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->andThrow(new \RuntimeException('activity table locked'));

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('Failed to record image audit event.', Mockery::on(fn(array $context): bool =>
                $context['site_id'] === 1
                && $context['user_id'] === 5
                && $context['action'] === 'image_attached'
            ));

        // Should not throw — image audit is non-critical.
        $this->service->recordAttach(1, 5, 10, 'block_1', 42);
        $this->assertTrue(true);
    }

    public function test_diff_and_record_reports_attach_replace_and_remove(): void
    {
        $previousBlocks = [
            ['id' => 'b1', 'type' => 'image', 'cms_image_id' => 1],
            ['id' => 'b2', 'type' => 'image', 'cms_image_id' => 2],
        ];
        $currentBlocks = [
            ['id' => 'b1', 'type' => 'image', 'cms_image_id' => 1],
            ['id' => 'b2', 'type' => 'image', 'cms_image_id' => 99],
            ['id' => 'b3', 'type' => 'image', 'cms_image_id' => 3],
        ];

        $this->activityRepository->shouldReceive('record')->times(2);

        $this->service->diffAndRecord(1, 5, 10, $previousBlocks, $currentBlocks);
        $this->assertTrue(true);
    }
}
