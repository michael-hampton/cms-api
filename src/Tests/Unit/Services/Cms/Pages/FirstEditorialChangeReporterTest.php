<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\FirstEditorialChangeReporter;
use Mockery;
use PHPUnit\Framework\TestCase;

class FirstEditorialChangeReporterTest extends TestCase
{
    private PageRepository $pageRepository;
    private FirstEditorialChangeReporter $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);

        $this->service = new FirstEditorialChangeReporter(
            $this->pageRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_reports_first_editorial_change_for_contributor_owned_page(): void
    {
        $page = new Page([
            'id' => 1,
            'contributor_id' => 7,
            'is_public_contribution' => true,
            'first_editorial_change_reported_at' => null,
        ]);

        $this->pageRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function (array $data): bool {
                return $data['first_editorial_change_reported_by'] === 99
                    && $data['first_editorial_change_history_id'] === 55
                    && !empty($data['first_editorial_change_reported_at']);
            }));

        $result = $this->service->reportIfNeeded($page, 99, 55);

        $this->assertTrue($result);
    }

    public function test_it_does_not_report_when_page_has_no_contributor(): void
    {
        $page = new Page([
            'id' => 1,
            'contributor_id' => null,
            'is_public_contribution' => true,
        ]);

        $this->pageRepository->shouldNotReceive('update');

        $this->assertFalse($this->service->reportIfNeeded($page, 99, 55));
    }

    public function test_it_does_not_report_when_page_is_not_public_contribution(): void
    {
        $page = new Page([
            'id' => 1,
            'contributor_id' => 7,
            'is_public_contribution' => false,
        ]);

        $this->pageRepository->shouldNotReceive('update');

        $this->assertFalse($this->service->reportIfNeeded($page, 99, 55));
    }

    public function test_it_does_not_report_when_already_reported(): void
    {
        $page = new Page([
            'id' => 1,
            'contributor_id' => 7,
            'is_public_contribution' => true,
            'first_editorial_change_reported_at' => '2026-06-08 10:00:00',
        ]);

        $this->pageRepository->shouldNotReceive('update');

        $this->assertFalse($this->service->reportIfNeeded($page, 99, 55));
    }

    public function test_it_does_not_report_when_actor_is_contributor(): void
    {
        $page = new Page([
            'id' => 1,
            'contributor_id' => 7,
            'is_public_contribution' => true,
            'first_editorial_change_reported_at' => null,
        ]);

        $this->pageRepository->shouldNotReceive('update');

        $this->assertFalse($this->service->reportIfNeeded($page, 7, 55));
    }

    public function test_it_does_not_report_without_history_id(): void
    {
        $page = new Page([
            'id' => 1,
            'contributor_id' => 7,
            'is_public_contribution' => true,
        ]);

        $this->pageRepository->shouldNotReceive('update');

        $this->assertFalse($this->service->reportIfNeeded($page, 99, 0));
    }
}