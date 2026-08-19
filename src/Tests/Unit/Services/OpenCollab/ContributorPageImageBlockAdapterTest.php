<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\ArticleApprovalService;
use App\Services\OpenCollab\ContributorPageService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ContributorPageImageBlockAdapterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_adapts_open_collab_image_block_to_cms_parser_contract(): void
    {
        $pageService = Mockery::mock(PageService::class);
        $created = $this->page();

        $pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(function (array $data, int $siteId): bool {
                $image = $data['blocks'][0];

                return $siteId === 7
                    && $image['type'] === 'image'
                    && $image['image_id'] === 39
                    && $image['src'] === 'http://localhost/storage/test.jpg'
                    && $image['alt'] === 'Test alt'
                    && $image['caption'] === ''
                    && $image['layout'] === 'full'
                    && $image['alignment'] === 'center';
            })
            ->andReturn($created);

        $service = $this->service($pageService);

        $result = $service->createPage([
            'forms' => [
                'main' => ['title' => 'Image article'],
                'meta' => ['status' => 'draft'],
            ],
            'blocks' => [[
                'type' => 'image',
                'order' => 0,
                'cms_image_id' => 39,
                'image_url' => 'http://localhost/storage/test.jpg',
                'thumbnail_url' => 'http://localhost/storage/thumb.jpg',
                'alt' => 'Test alt',
                'caption' => '',
                'layout' => 'full',
                'alignment' => 'center',
            ]],
        ], 12, 7);

        $this->assertSame($created, $result);
    }

    public function test_create_adapts_image_blocks_nested_in_gallery_slides(): void
    {
        $pageService = Mockery::mock(PageService::class);
        $created = $this->page();

        $pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(function (array $data): bool {
                $image = $data['gallery_slides'][0]['blocks'][0];

                return $image['image_id'] === 55
                    && $image['src'] === '/storage/gallery.jpg';
            })
            ->andReturn($created);

        $service = $this->service($pageService);

        $service->createPage([
            'forms' => [
                'main' => ['title' => 'Gallery article'],
                'meta' => ['status' => 'draft'],
            ],
            'gallery_slides' => [[
                'image_id' => 1,
                'title' => 'Slide',
                'blocks' => [[
                    'type' => 'image',
                    'cms_image_id' => 55,
                    'thumbnail_url' => '/storage/gallery.jpg',
                    'alt' => 'Gallery image',
                ]],
            ]],
        ], 12, 7);

        $this->addToAssertionCount(1);
    }

    private function service(PageService $pageService): ContributorPageService
    {
        $activity = Mockery::mock(ActivityRepository::class);
        $activity->shouldReceive('record')->andReturnNull()->byDefault();

        $users = Mockery::mock(UserRepositoryInterface::class);
        $users->shouldReceive('find')->andReturnNull()->byDefault();

        $database = Mockery::mock(Database::class);
        $database->shouldReceive('transaction')
            ->byDefault()
            ->andReturnUsing(static fn(callable $callback) => $callback());

        $logger = Mockery::mock(Logger::class);
        $logger->shouldIgnoreMissing();

        return new ContributorPageService(
            $pageService,
            Mockery::mock(PageRepository::class),
            Mockery::mock(ArticleApprovalService::class),
            $activity,
            Mockery::mock(AuthorRepository::class),
            Mockery::mock(PageAuthorRepository::class),
            $users,
            $database,
            $logger,
        );
    }

    private function page(): Page
    {
        $page = new Page([
            'id' => 101,
            'site_id' => 7,
            'title' => 'Image article',
            'status' => 'draft',
            'contributor_id' => 12,
        ]);
        $page->exists = true;

        return $page;
    }
}
