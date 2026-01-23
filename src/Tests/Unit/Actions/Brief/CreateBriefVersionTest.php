<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\CreateBriefVersion;
use App\Framework\Support\Collection;
use App\Models\Brief;
use App\Models\BriefVersion;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefVersionRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class CreateBriefVersionTest extends TestCase
{
    private BriefRepository $briefRepository;
    private BriefVersionRepository $versionRepository;
    private CreateBriefVersion $service;

    public function test_create_version_creates_first_version(): void
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->title = 'Test Brief';
        $brief->description = 'Description';
        $brief->target_word_count = 1500;
        $brief->seo_keywords = 'test, keywords';
        $brief->target_audience = 'General';
        $brief->attachments = new Collection([1, 2, 3]);
        $brief->comments = new Collection([1, 2]);

        $this->briefRepository
            ->shouldReceive('getCompleteBriefData')
            ->once()
            ->with(1)
            ->andReturn($brief);

        $this->versionRepository
            ->shouldReceive('getLatest')
            ->once()
            ->with(1)
            ->andReturn(null);

        $version = Mockery::mock(BriefVersion::class);

        $this->versionRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['brief_id'] === 1 &&
                    $data['version_number'] === 1 &&
                    $data['title'] === 'Test Brief' &&
                    $data['created_by'] === 10 &&
                    $data['change_summary'] === 'Initial version' &&
                    $data['data']['attachments_count'] === 3 &&
                    $data['data']['comments_count'] === 2;
            }))
            ->andReturn($version);

        $result = $this->service->handle(1, 10, 'Initial version');

        $this->assertSame($version, $result);
    }

    public function test_create_version_increments_version_number(): void
    {
        $brief = Mockery::mock(Brief::class)->makePartial();
        $brief->title = 'Test Brief';
        $brief->description = 'Description';
        $brief->target_word_count = 1500;
        $brief->seo_keywords = 'test';
        $brief->target_audience = 'General';
        $brief->attachments = new Collection();
        $brief->comments = new Collection();

        $latestVersion = Mockery::mock(BriefVersion::class)->makePartial();
        $latestVersion->version_number = 5;

        $this->briefRepository
            ->shouldReceive('getCompleteBriefData')
            ->once()
            ->andReturn($brief);

        $this->versionRepository
            ->shouldReceive('getLatest')
            ->once()
            ->andReturn($latestVersion);

        $this->versionRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['version_number'] === 6;
            }))
            ->andReturn(Mockery::mock(BriefVersion::class));

        $result = $this->service->handle(1, 10);

        $this->assertInstanceOf(BriefVersion::class, $result);
    }

    public function test_create_version_throws_exception_when_brief_not_found(): void
    {
        $this->briefRepository
            ->shouldReceive('getCompleteBriefData')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Brief not found: 999');

        $this->service->handle(999, 1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->briefRepository = Mockery::mock(BriefRepository::class);
        $this->versionRepository = Mockery::mock(BriefVersionRepository::class);

        $this->service = new CreateBriefVersion(
            $this->briefRepository,
            $this->versionRepository
        );
    }
}