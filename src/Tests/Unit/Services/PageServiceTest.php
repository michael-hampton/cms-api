<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Exceptions\ValidationException;
use App\Models\Block;
use App\Models\Page;
use App\Repositories\AccessRoleRepository;
use App\Repositories\BlockRepository;
use App\Repositories\PageCategoryRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Repositories\PageMetadataRepository;
use App\Repositories\PageRepository;
use App\Repositories\PageSeoRepository;
use App\Repositories\PageSettingsRepository;
use App\Repositories\PageSocialRepository;
use App\Repositories\PageTagRepository;
use App\Services\BlockParserService;
use App\Services\PageService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase
{
    private $pageRepository;
    private $blockRepository;
    private $blockParserService;
    private $metadataRepository;
    private $seoRepository;
    private $settingsRepository;
    private $socialRepository;
    private $categoryRepository;
    private $customFieldRepository;
    private $tagRepository;
    private $accessRoleRepository;
    private $database;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->blockParserService = Mockery::mock(BlockParserService::class);
        $this->database = Mockery::mock(Database::class);

        parent::setUp();
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->blockParserService = Mockery::mock(BlockParserService::class);
        $this->metadataRepository = Mockery::mock(PageMetadataRepository::class);
        $this->seoRepository = Mockery::mock(PageSeoRepository::class);
        $this->settingsRepository = Mockery::mock(PageSettingsRepository::class);
        $this->socialRepository = Mockery::mock(PageSocialRepository::class);
        $this->categoryRepository = Mockery::mock(PageCategoryRepository::class);
        $this->customFieldRepository = Mockery::mock(PageCustomFieldRepository::class);
        $this->tagRepository = Mockery::mock(PageTagRepository::class);
        $this->accessRoleRepository = Mockery::mock(AccessRoleRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new PageService(
            $this->pageRepository,
            $this->blockRepository,
            $this->blockParserService,
            $this->metadataRepository,
            $this->seoRepository,
            $this->settingsRepository,
            $this->socialRepository,
            $this->categoryRepository,
            $this->customFieldRepository,
            $this->tagRepository,
            $this->accessRoleRepository,
            $this->database
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testDeletePageDeletesBlocksAndPage()
    {
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->blockRepository->shouldReceive('deletePageBlocks')
            ->with(1)
            ->once();

        $this->pageRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->deletePage(1);

        $this->assertTrue($result);
    }

    public function testDuplicatePageCreatesNewPageWithCopySuffix()
    {
        // Original page as a real object (not a Mockery partial)
        $originalPage = Mockery::mock(Page::class)->makePartial();
        $originalPage->id = 1;
        $originalPage->title = 'Original Page';
        $originalPage->slug = 'original-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = 'Meta Title';
        $originalPage->meta_description = 'Meta Description';
        $originalPage->blocks = collect([
            (object)[
                'type' => 'text',
                'data' => ['content' => 'Hello'],
                'order' => 1
            ]
        ]);

        // Simulate relationLoaded
        $this->service = Mockery::mock(get_class($this->service), [
            $this->pageRepository,
            $this->blockRepository,
            $this->blockParserService,
            Mockery::mock(\App\Repositories\PageMetadataRepository::class),
            Mockery::mock(\App\Repositories\PageSeoRepository::class),
            Mockery::mock(\App\Repositories\PageSettingsRepository::class),
            Mockery::mock(\App\Repositories\PageSocialRepository::class),
            Mockery::mock(\App\Repositories\PageCategoryRepository::class),
            Mockery::mock(\App\Repositories\PageCustomFieldRepository::class),
            Mockery::mock(\App\Repositories\PageTagRepository::class),
            Mockery::mock(\App\Repositories\AccessRoleRepository::class),
            $this->database
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $newPage = new Page();
        $newPage->id = 2;

        $this->service->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($originalPage);
        $this->service->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($newPage);

        // Transaction callback
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Page creation
        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['title' => 'Original Page (Copy)']))
            ->andReturn($newPage);

        $originalPage->shouldReceive('relationLoaded')
            ->with('blocks')
            ->andReturn(true);

        $blocksCollection = collect([
            (object)[
                'type' => 'text',
                'data' => ['content' => 'Hello'],
                'order' => 1
            ]
        ]);

        $originalPage->shouldReceive('getRelation')
            ->with('blocks')
            ->andReturn($blocksCollection);

        // Block creation
        $this->blockRepository->shouldReceive('create')
            ->once()
            ->with([
                'page_id' => 2,
                'type' => 'text',
                'data' => ['content' => 'Hello'],
                'order' => 1
            ])
            ->andReturn(Mockery::mock(Block::class));

        $result = $this->service->duplicatePage(1);

        $this->assertSame($newPage, $result);
    }

    public function testSearchPagesFiltersCorrectly()
    {
        $query = 'test query';
        $category = '';
        $tag = '';
        $status = 'published';

        // Mock repository to return empty collection using quickSearch
        $this->pageRepository->shouldReceive('quickSearch')
            ->with($query, [
                'status' => $status,
                'with' => ['categories', 'tags']
            ])
            ->once()
            ->andReturn(collect([]));

        // Call the service
        $result = $this->service->searchPages($query, $category, $tag, $status);

        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function testGetCompletePageDataLoadsAllRelations()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($page);

        $result = $this->service->getCompletePageData(1);

        $this->assertSame($page, $result);
    }

//    public function testCreatePageWithAllDataCreatesPageAndAllRelations()
//    {
//        $requestData = [
//            'forms' => [
//                'main' => ['title' => 'Test Page'],
//                'meta' => ['slug' => 'test-page', 'status' => 'draft', 'author' => 1],
//                'seo' => ['meta_title' => 'SEO Title', 'meta_description' => 'SEO Desc'],
//                'settings' => ['template' => 'default'],
//                'social' => ['enable_sharing' => true],
//                'tags' => ['categories' => [1], 'tags' => [2]]
//            ],
//            'blocks' => [
//                ['type' => 'text', 'data' => ['content' => 'Hello'], 'order' => 1]
//            ]
//        ];
//
//        $newPage = Mockery::mock(Page::class)->makePartial();
//        $newPage->id = 1;
//
//        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
//            return $callback();
//        });
//
//        $this->metadataRepository->shouldReceive('createOrUpdate')->once();
//        $this->seoRepository->shouldReceive('createOrUpdate')->once();
//        $this->settingsRepository->shouldReceive('createOrUpdate')->once();
//        $this->accessRoleRepository->shouldReceive('syncAccessRoles')->never();
//        $this->socialRepository->shouldReceive('createOrUpdate')->once();
//        $this->categoryRepository->shouldReceive('syncCategories')->once()->with(1, [1]);
//        $this->tagRepository->shouldReceive('syncTags')->once()->with(1, [2]);
//        $this->customFieldRepository->shouldReceive('syncCustomFields')->never();
//        $this->blockParserService->shouldReceive('replacePageBlocks')->once()->with(1, Mockery::any());
//
//        Page::shouldReceive('with')->andReturnSelf();
//        Page::shouldReceive('find')->with(1)->andReturn($newPage);
//
//        $result = $this->service->createPageWithAllData($requestData);
//
//        $this->assertSame($newPage, $result);
//    }

    public function testUpdatePageWithAllDataUpdatesExistingPage()
    {
        $requestData = [
            'forms' => [
                'main' => ['title' => 'Updated Page'],
                'meta' => ['slug' => 'updated-page', 'status' => 'published']
            ]
        ];

        $existingPage = Mockery::mock(Page::class)->makePartial();
        $existingPage->id = 1;

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($existingPage);

        $this->pageRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset(['title' => 'Updated Page']))
            ->andReturn($existingPage);

        $this->metadataRepository->shouldReceive('createOrUpdate')->once();

        $this->blockParserService->shouldReceive('replacePageBlocks')->never();

        $result = $this->service->updatePageWithAllData(1, $requestData);

        $this->assertSame($existingPage, $result);
    }

    public function testDuplicatePageReturnsNullForNonexistent()
    {
        $service = Mockery::mock(get_class($this->service), [
            $this->pageRepository, $this->blockRepository, $this->blockParserService,
            $this->metadataRepository, $this->seoRepository, $this->settingsRepository,
            $this->socialRepository, $this->categoryRepository, $this->customFieldRepository,
            $this->tagRepository, $this->accessRoleRepository, $this->database
        ])->makePartial();

        $this->pageRepository->shouldReceive('getCompletePageData')->with(999)->andReturn(null);

        $result = $this->service->duplicatePage(999);

        $this->assertNull($result);
    }

    public function testSearchPagesCallsRepository()
    {
        $expectedCollection = collect([]);

        // Updated to use quickSearch
        $this->pageRepository->shouldReceive('quickSearch')
            ->with('query', [
                'status' => 'published',
                'with' => ['categories', 'tags']
            ])
            ->once()
            ->andReturn($expectedCollection);

        $result = $this->service->searchPages('query', 'category', 'tag', 'published');

        $this->assertSame($expectedCollection, $result);
    }

    public function testSearchPagesWithLimit()
    {
        $expectedCollection = collect([]);

        $this->pageRepository->shouldReceive('quickSearch')
            ->with('query', [
                'status' => 'published',
                'limit' => 10,
                'with' => ['categories', 'tags']
            ])
            ->once()
            ->andReturn($expectedCollection);

        $result = $this->service->searchPages('query', '', '', 'published', 10);

        $this->assertSame($expectedCollection, $result);
    }

    public function testFindPageBySlugReturnsCompletePageData()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $this->pageRepository->shouldReceive('findBySlug')
            ->with('test-slug')
            ->once()
            ->andReturn($page);

        $this->service = Mockery::mock(get_class($this->service), [
            $this->pageRepository, $this->blockRepository, $this->blockParserService,
            $this->metadataRepository, $this->seoRepository, $this->settingsRepository,
            $this->socialRepository, $this->categoryRepository, $this->customFieldRepository,
            $this->tagRepository, $this->accessRoleRepository, $this->database
        ])->makePartial();

        $this->service->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($page);

        $result = $this->service->findPageBySlug('test-slug');

        $this->assertSame($page, $result);
    }

    public function testFindPageBySlugReturnsNullForNonexistent()
    {
        $this->pageRepository->shouldReceive('findBySlug')
            ->with('nonexistent')
            ->once()
            ->andReturn(null);

        $result = $this->service->findPageBySlug('nonexistent');

        $this->assertNull($result);
    }

    public function testProcessMetadataFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $pageId = 1;
        $metaForm = [
            'content_type' => 'article',
            'author' => 1,
            'publish_date' => '2025-01-01 00:00:00',
            'featured' => true,
            'allow_comments' => true
        ];

        $this->metadataRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with($pageId, Mockery::subset([
                'content_type' => 'article',
                'author' => 1,
                'featured' => true,
                'allow_comments' => true
            ]));

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('processMetadataForm');
        $method->setAccessible(true);
        $method->invoke($this->service, $pageId, $metaForm);
    }

    public function testProcessSeoFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $pageId = 1;
        $seoForm = [
            'meta_keywords' => 'php, testing',
            'canonical_url' => 'https://example.com/page',
            'no_index' => true,
            'og_title' => 'OG Title'
        ];

        $this->seoRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with($pageId, Mockery::subset([
                'meta_keywords' => 'php, testing',
                'canonical_url' => 'https://example.com/page',
                'no_index' => true,
                'og_title' => 'OG Title'
            ]));

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('processSeoForm');
        $method->setAccessible(true);
        $method->invoke($this->service, $pageId, $seoForm);
    }

    public function testProcessSettingsFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $pageId = 1;
        $settingsForm = [
            'template' => 'custom',
            'menu_order' => 5,
            'price' => 99.99,
            'recurring' => true,
            'access_roles' => ['admin', 'editor']
        ];

        $this->settingsRepository->shouldReceive('createOrUpdate')->once();
        $this->accessRoleRepository->shouldReceive('syncAccessRoles')
            ->once()
            ->with($pageId, ['admin', 'editor']);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('processSettingsForm');
        $method->setAccessible(true);
        $method->invoke($this->service, $pageId, $settingsForm);
    }

    public function testProcessSocialFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $pageId = 1;
        $socialForm = [
            'enable_sharing' => true,
            'platforms' => ['facebook', 'twitter'],
            'track_shares' => true
        ];

        $this->socialRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with($pageId, Mockery::on(function ($data) {
                return $data['enable_sharing'] === true
                    && $data['track_shares'] === true;
            }));

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('processSocialForm');
        $method->setAccessible(true);
        $method->invoke($this->service, $pageId, $socialForm);
    }

    public function testProcessTagsFormSyncsAllRelations()
    {
        $this->expectNotToPerformAssertions();

        $pageId = 1;
        $tagsForm = [
            'categories' => [1, 2],
            'tags' => [3, 4],
            'customFields' => [
                ['key' => 'color', 'value' => 'red', 'type' => 'text']
            ]
        ];

        $this->categoryRepository->shouldReceive('syncCategories')->once()->with($pageId, [1, 2]);
        $this->tagRepository->shouldReceive('syncTags')->once()->with($pageId, [3, 4]);
        $this->customFieldRepository->shouldReceive('syncCustomFields')->once();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('processTagsForm');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, $pageId, $tagsForm);
    }
}