<?php

namespace App\Tests\Unit\Actions\Brief;

use App\Actions\Brief\DuplicateBrief;
use App\Actions\Brief\LogBriefActivity;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Repositories\Cms\Briefs\BriefRepository;
use Mockery;
use PHPUnit\Framework\TestCase;

class DuplicateBriefTest extends TestCase
{
    private BriefRepository $briefRepository;
    private LogBriefActivity $activityService;
    private Database $database;
    private DuplicateBrief $service;

    public function test_duplicate_creates_copy_with_attachments(): void
    {
        $originalBrief = Mockery::mock(Brief::class)->makePartial();
        $originalBrief->id = 1;
        $originalBrief->site_id = 5;
        $originalBrief->title = 'Original Brief';
        $originalBrief->description = 'Description';
        $originalBrief->category_id = 3;
        $originalBrief->target_word_count = 1000;
        $originalBrief->seo_keywords = 'seo';
        $originalBrief->target_audience = 'audience';
        $originalBrief->template_id = 2;

        $attachment1 = Mockery::mock(BriefAttachment::class)->makePartial();
        $attachment1->type = 'image';
        $attachment1->file_url = '/path/to/image.jpg';
        $attachment1->file_name = 'image.jpg';
        $attachment1->image_id = 10;
        $attachment1->product_id = null;
        $attachment1->url = null;
        $attachment1->metadata = ['alt' => 'test'];
        $attachment1->sort_order = 1;

        $originalBrief->attachments = collect([$attachment1]);

        $newBrief = Mockery::mock(Brief::class)->makePartial();
        $newBrief->id = 2;

        $finalBrief = Mockery::mock(Brief::class);

        // Mock transaction
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->once()
            ->with(1)
            ->andReturn($originalBrief);

        $this->briefRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['title'] === 'Original Brief (Copy)' &&
                    $data['status'] === 'draft' &&
                    $data['owner_id'] === 99;
            }))
            ->andReturn($newBrief);

        $this->briefRepository
            ->shouldReceive('addAttachment')
            ->once()
            ->with(2, Mockery::on(function ($data) {
                return $data['type'] === 'image' &&
                    $data['image_id'] === 10;
            }));

        $this->activityService
            ->shouldReceive('handle')
            ->once()
            ->with(2, 99, 'duplicated', 'Duplicated from brief #1');

        $this->briefRepository
            ->shouldReceive('getWithRelations')
            ->once()
            ->with(2)
            ->andReturn($finalBrief);

        $result = $this->service->handle(1, 99);

        $this->assertSame($finalBrief, $result);
    }

    public function test_duplicate_throws_exception_when_brief_not_found(): void
    {
        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->briefRepository
            ->shouldReceive('getWithRelations')
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
        $this->activityService = Mockery::mock(LogBriefActivity::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new DuplicateBrief(
            $this->briefRepository,
            $this->activityService,
            $this->database
        );
    }
}