<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\ArticleQualityScore;
use App\Repositories\OpenCollab\ArticleQualityScoreRepository;
use App\Services\OpenCollab\ReadabilityAnalyser;
use App\Services\OpenCollab\ReadabilityService;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class ReadabilityServiceTest extends UnitTestCase
{

    private $analyser;
    private $repository;
    private $service;

    public function test_score_article_calculates_and_persists()
    {
        $content = "Sample content";
        $pageId = 123;
        $calculatedScore = 75.5;
        $mockModel = new ArticleQualityScore();

        $this->analyser->shouldReceive('analyse')
            ->with($content)
            ->once()
            ->andReturn($calculatedScore);

        $this->repository->shouldReceive('upsert')
            ->with($pageId, $calculatedScore)
            ->once()
            ->andReturn($mockModel);

        $result = $this->service->scoreArticle($pageId, $content);
        $this->assertSame($mockModel, $result);
    }

    public function test_get_score_delegates_to_repository()
    {
        $pageId = 123;
        $mockModel = new ArticleQualityScore();

        $this->repository->shouldReceive('findByPageId')
            ->with($pageId)
            ->once()
            ->andReturn($mockModel);

        $this->assertSame($mockModel, $this->service->getScore($pageId));
    }

    protected function setUp(): void
    {
        $this->analyser = Mockery::mock(ReadabilityAnalyser::class);
        $this->repository = Mockery::mock(ArticleQualityScoreRepository::class);
        $this->service = new ReadabilityService($this->analyser, $this->repository);
    }
}