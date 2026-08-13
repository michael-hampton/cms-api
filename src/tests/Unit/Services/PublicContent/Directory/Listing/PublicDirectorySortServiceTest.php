<?php

namespace App\Tests\Unit\Services\PublicContent\Directory\Listing;

use App\Enums\PublicContent\PublicDirectoryIndexSort;
use App\Enums\PublicContent\PublicDirectoryPageSort;
use App\Framework\Database\QueryBuilder;
use App\Services\PublicContent\Directory\Listing\PublicDirectorySortService;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicDirectorySortServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[DataProvider('indexSorts')]
    public function test_apply_index_sort(PublicDirectoryIndexSort $sort, string $column, string $direction): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('orderBy')->once()->with($column, $direction)->andReturnSelf();

        $result = (new PublicDirectorySortService())->applyIndexSort($query, $sort);

        self::assertSame($query, $result);
    }

    public static function indexSorts(): array
    {
        return [
            'name asc' => [PublicDirectoryIndexSort::NameAsc, 'name', 'asc'],
            'name desc' => [PublicDirectoryIndexSort::NameDesc, 'name', 'desc'],
            'newest' => [PublicDirectoryIndexSort::Newest, 'created_at', 'desc'],
            'oldest' => [PublicDirectoryIndexSort::Oldest, 'created_at', 'asc'],
        ];
    }

    public function test_apply_index_sort_most_articles_counts_published_pages(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('withCount')->once()->with('pages', Mockery::type('callable'))->andReturnUsing(
            function (string $relation, callable $callback) use ($query) {
                $inner = Mockery::mock(QueryBuilder::class);
                $inner->shouldReceive('where')->once()->with('status', 'published')->andReturnSelf();
                $callback($inner);

                return $query;
            },
        );
        $query->shouldReceive('orderByRaw')->once()->with('pages_count desc')->andReturnSelf();

        $result = (new PublicDirectorySortService())->applyIndexSort($query, PublicDirectoryIndexSort::MostArticles);

        self::assertSame($query, $result);
    }

    #[DataProvider('pageSorts')]
    public function test_apply_page_sort(PublicDirectoryPageSort $sort, string $column, string $direction): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('orderBy')->once()->with($column, $direction)->andReturnSelf();

        $result = (new PublicDirectorySortService())->applyPageSort($query, $sort);

        self::assertSame($query, $result);
    }

    public static function pageSorts(): array
    {
        return [
            'newest' => [PublicDirectoryPageSort::Newest, 'published_at', 'desc'],
            'oldest' => [PublicDirectoryPageSort::Oldest, 'published_at', 'asc'],
            'title asc' => [PublicDirectoryPageSort::TitleAsc, 'title', 'asc'],
            'title desc' => [PublicDirectoryPageSort::TitleDesc, 'title', 'desc'],
        ];
    }

    public function test_apply_page_sort_most_viewed_counts_views(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('withCount')->once()->with('views')->andReturnSelf();
        $query->shouldReceive('orderByRaw')->once()->with('views_count desc')->andReturnSelf();

        $result = (new PublicDirectorySortService())->applyPageSort($query, PublicDirectoryPageSort::MostViewed);

        self::assertSame($query, $result);
    }

    public function test_apply_page_sort_most_commented_counts_approved_comments(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('withCount')->once()->with('comments', Mockery::type('callable'))->andReturnUsing(
            function (string $relation, callable $callback) use ($query) {
                $inner = Mockery::mock(QueryBuilder::class);
                $inner->shouldReceive('where')->once()->with('status', 'approved')->andReturnSelf();
                $callback($inner);

                return $query;
            },
        );
        $query->shouldReceive('orderByRaw')->once()->with('comments_count desc')->andReturnSelf();

        $result = (new PublicDirectorySortService())->applyPageSort($query, PublicDirectoryPageSort::MostCommented);

        self::assertSame($query, $result);
    }
}