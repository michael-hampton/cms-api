<?php
declare(strict_types=1);

namespace App\Tests\Unit\Services\PublicContent\Directory\Listing;

use App\Enums\PublicContent\PublicDirectoryIndexSort;
use App\Enums\PublicContent\PublicDirectoryPageSort;
use App\Framework\Database\QueryBuilder;
use App\Services\PublicContent\Directory\Listing\PublicDirectorySortService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PublicDirectorySortServiceTest extends MockeryTestCase
{
    private PublicDirectorySortService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PublicDirectorySortService();
    }

    #[DataProvider('indexSortDataProvider')]
    public function testApplyIndexSortBasicCases(PublicDirectoryIndexSort $sort, string $column, string $direction): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('orderBy')
            ->once()
            ->with($column, $direction)
            ->andReturnSelf();

        $result = $this->service->applyIndexSort($query, $sort);
        $this->assertSame($query, $result);
    }

    public static function indexSortDataProvider(): array
    {
        return [
            'NameAsc case'  => [PublicDirectoryIndexSort::NameAsc, 'name', 'asc'],
            'NameDesc case' => [PublicDirectoryIndexSort::NameDesc, 'name', 'desc'],
            'Newest case'   => [PublicDirectoryIndexSort::Newest, 'created_at', 'desc'],
            'Oldest case'   => [PublicDirectoryIndexSort::Oldest, 'created_at', 'asc'],
        ];
    }

    public function testApplyIndexSortMostArticlesCase(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $relationQuery = Mockery::mock(QueryBuilder::class);

        $relationQuery->shouldReceive('where')
            ->once()
            ->with('status', 'published')
            ->andReturnSelf();

        $query->shouldReceive('withCount')
            ->once()
            ->withArgs(function (string $relation, callable $callback) use ($relationQuery) {
                if ($relation !== 'pages') {
                    return false;
                }
                $callback($relationQuery);
                return true;
            })
            ->andReturnSelf();

        $query->shouldReceive('orderByRaw')
            ->once()
            ->with('pages_count desc')
            ->andReturnSelf();

        $result = $this->service->applyIndexSort($query, PublicDirectoryIndexSort::MostArticles);
        $this->assertSame($query, $result);
    }

    #[DataProvider('pageSortDataProvider')]
    public function testApplyPageSortBasicCases(PublicDirectoryPageSort $sort, string $column, string $direction): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('orderBy')
            ->once()
            ->with($column, $direction)
            ->andReturnSelf();

        $result = $this->service->applyPageSort($query, $sort);
        $this->assertSame($query, $result);
    }

    public static function pageSortDataProvider(): array
    {
        return [
            'Newest case'    => [PublicDirectoryPageSort::Newest, 'published_at', 'desc'],
            'Oldest case'    => [PublicDirectoryPageSort::Oldest, 'published_at', 'asc'],
            'TitleAsc case'  => [PublicDirectoryPageSort::TitleAsc, 'title', 'asc'],
            'TitleDesc case' => [PublicDirectoryPageSort::TitleDesc, 'title', 'desc'],
        ];
    }

    public function testApplyPageSortMostViewedCase(): void
    {
        $query = Mockery::mock(QueryBuilder::class);

        $query->shouldReceive('withCount')
            ->once()
            ->with('views')
            ->andReturnSelf();

        $query->shouldReceive('orderByRaw')
            ->once()
            ->with('views_count desc')
            ->andReturnSelf();

        $result = $this->service->applyPageSort($query, PublicDirectoryPageSort::MostViewed);
        $this->assertSame($query, $result);
    }

    public function testApplyPageSortMostCommentedCase(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $relationQuery = Mockery::mock(QueryBuilder::class);

        $relationQuery->shouldReceive('where')
            ->once()
            ->with('status', 'approved')
            ->andReturnSelf();

        $query->shouldReceive('withCount')
            ->once()
            ->withArgs(function (string $relation, callable $callback) use ($relationQuery) {
                if ($relation !== 'comments') {
                    return false;
                }
                $callback($relationQuery);
                return true;
            })
            ->andReturnSelf();

        $query->shouldReceive('orderByRaw')
            ->once()
            ->with('comments_count desc')
            ->andReturnSelf();

        $result = $this->service->applyPageSort($query, PublicDirectoryPageSort::MostCommented);
        $this->assertSame($query, $result);
    }
}