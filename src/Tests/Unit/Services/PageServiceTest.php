<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
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
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

class PageServiceTest extends FunctionalTestCase
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
    private $databaseMock;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->blockRepository = Mockery::mock(BlockRepository::class);
        $this->blockParserService = Mockery::mock(BlockParserService::class);
        $this->databaseMock = Mockery::mock(Database::class);

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
        $this->databaseMock = Mockery::mock(Database::class);

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
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testDeletePageDeletesBlocksAndPage()
    {
        $this->databaseMock->shouldReceive('transaction')
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
        $originalPage = $this->createMockPage(1, 'Original Page');
        $originalPage->slug = 'original-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = 'Meta Title';
        $originalPage->meta_description = 'Meta Description';

        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->once()
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return strpos($data['title'], '(Copy)') !== false
                    && strpos($data['slug'], '-copy-') !== false
                    && $data['status'] === 'draft';
            }))
            ->andReturn($newPage);

        // All duplication methods
        $this->pageRepository->shouldReceive('duplicateBlocks')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateTags')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->with(1, 2)->once();

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->once()
            ->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertSame($newPage, $result);
    }

    public function testSearchPagesCallsQuickSearch()
    {
        $expectedCollection = collect([]);

        $this->pageRepository->shouldReceive('quickSearch')
            ->with('test query', [
                'status' => 'published',
                'with' => ['categories', 'tags']
            ])
            ->once()
            ->andReturn($expectedCollection);

        $result = $this->service->searchPages('test query', '', '', 'published');

        $this->assertSame($expectedCollection, $result);
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

    public function testCreatePageWithAllDataCreatesPageAndAllRelations()
    {
        $requestData = [
            'forms' => [
                'main' => ['title' => 'Test Page'],
                'meta' => ['slug' => 'test-page', 'status' => 'draft', 'author' => 1],
                'seo' => ['meta_title' => 'SEO Title', 'meta_description' => 'SEO Desc'],
                'settings' => ['template' => 'default'],
                'social' => ['enable_sharing' => true],
                'tags' => ['categories' => [1], 'tags' => [2]]
            ],
            'blocks' => [
                ['type' => 'text', 'data' => ['content' => 'Hello'], 'order' => 1]
            ]
        ];

        $newPage = $this->createMockPage(1, 'Test Page');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->pageRepository->shouldReceive('create')->with(['title' => 'Test Page', 'slug' => 'test-page', 'status' => 'draft', 'meta_title' => 'SEO Title', 'meta_description' => 'SEO Desc', 'site_id' => $this->siteId])->once()->andReturn($newPage);

        // Mock all the repository calls
        $this->metadataRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::any());

        $this->seoRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::any());

        $this->settingsRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::any());

        $this->socialRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::any());

        $this->categoryRepository->shouldReceive('syncCategories')
            ->once()
            ->with(1, [1], $this->siteId);

        $this->tagRepository->shouldReceive('syncTags')
            ->once()
            ->with(1, [2], $this->siteId);

        $this->blockParserService->shouldReceive('replacePageBlocks')
            ->once()
            ->with(1, Mockery::any());

        // Mock getCompletePageData which is called at the end
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->once()
            ->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertSame($newPage, $result);
    }

    public function testUpdatePageWithAllDataUpdatesExistingPage()
    {
        $requestData = [
            'forms' => [
                'main' => ['title' => 'Updated Page'],
                'meta' => ['slug' => 'updated-page', 'status' => 'published']
            ]
        ];

        $existingPage = $this->createMockPage(1, 'Updated Page');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->pageRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::subset(['title' => 'Updated Page']))
            ->andReturn($existingPage);

        $this->metadataRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::any());

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->once()
            ->andReturn($existingPage);

        $result = $this->service->updatePageWithAllData(1, $requestData, $this->siteId);;

        $this->assertSame($existingPage, $result);
    }

    public function testDuplicatePageReturnsNullForNonexistent()
    {
        $service = Mockery::mock(get_class($this->service), [
            $this->pageRepository, $this->blockRepository, $this->blockParserService,
            $this->metadataRepository, $this->seoRepository, $this->settingsRepository,
            $this->socialRepository, $this->categoryRepository, $this->customFieldRepository,
            $this->tagRepository, $this->accessRoleRepository, $this->databaseMock
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
            $this->tagRepository, $this->accessRoleRepository, $this->databaseMock
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

        $this->categoryRepository->shouldReceive('syncCategories')->once()->with($pageId, [1, 2], $this->siteId);
        $this->tagRepository->shouldReceive('syncTags')->once()->with($pageId, [3, 4], $this->siteId);
        $this->customFieldRepository->shouldReceive('syncCustomFields')->once();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('processTagsForm');
        $method->setAccessible(true);
        $result = $method->invoke($this->service, $pageId, $tagsForm, $this->siteId);
    }

    public function testDuplicatePageClonesAllRelations()
    {
        $originalPage = Mockery::mock(Page::class)->makePartial();
        $originalPage->id = 1;
        $originalPage->title = 'Original Page';
        $originalPage->slug = 'original-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = 'Meta Title';
        $originalPage->meta_description = 'Meta Description';

        $newPage = Mockery::mock(Page::class)->makePartial();
        $newPage->id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->once()
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['title' => 'Original Page (Copy)', 'status' => 'draft']))
            ->andReturn($newPage);

        // Expect all relation duplication methods to be called
        $this->pageRepository->shouldReceive('duplicateBlocks')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateTags')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->with(1, 2)->once();

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->once()
            ->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertSame($newPage, $result);
    }

    public function testDuplicatePageCreatesPageWithCopyInTitle()
    {
        $originalPage = Mockery::mock(Page::class)->makePartial();
        $originalPage->id = 1;
        $originalPage->title = 'Test Page';
        $originalPage->slug = 'test-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = null;
        $originalPage->meta_description = null;

        $newPage = Mockery::mock(Page::class)->makePartial();
        $newPage->id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return strpos($data['title'], '(Copy)') !== false
                    && strpos($data['slug'], '-copy-') !== false
                    && $data['status'] === 'draft';
            }))
            ->andReturn($newPage);

        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertNotNull($result);
    }

    public function testDuplicatePageSetsDraftStatus()
    {
        $originalPage = Mockery::mock(Page::class)->makePartial();
        $originalPage->id = 1;
        $originalPage->title = 'Published Page';
        $originalPage->slug = 'published-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = 'Meta';
        $originalPage->meta_description = 'Desc';

        $newPage = Mockery::mock(Page::class)->makePartial();
        $newPage->id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')
            ->with(Mockery::subset(['status' => 'draft']))
            ->andReturn($newPage);

        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertNotNull($result);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithMetadata()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        // ASSERTION 1: Explicitly assert the target method is called once.
        $this->pageRepository->shouldReceive('duplicateMetadata')
            ->with(1, 2)
            ->once();

        // ASSERTIONS 2-9: Explicitly assert the rest are called once to verify orchestration.
        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSeo()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateSeo')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSettings()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateSettings')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSocial()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateSocial')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithCategories()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateCategories')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithTags()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateTags')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithCustomFields()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateCustomFields')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithAccessRoles()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->pageRepository->shouldReceive('duplicateAccessRoles')
            ->with(1, 2)
            ->once();

        $this->service->duplicatePage(1);
    }

//    public function testDuplicatePageReturnsNullForNonexistent()
//    {
//        $this->pageRepository->shouldReceive('getCompletePageData')
//            ->with(999)
//            ->andReturn(null);
//
//        $result = $this->service->duplicatePage(999);
//
//        $this->assertNull($result);
//    }

    // Helper methods
    private function createMockPage(int $id): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = $id;
        $page->title = 'Test';
        $page->slug = 'test';
        $page->status = 'published';
        $page->meta_title = null;
        $page->meta_description = null;
        return $page;
    }

    private function setupDuplicatePageExpectations(Page $originalPage, Page $newPage): void
    {
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($originalPage->id)
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        // CHANGE: Use byDefault() to allow other tests to set explicit, asserted counts.
        $this->pageRepository->shouldReceive('duplicateBlocks')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateMetadata')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSeo')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSettings')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSocial')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCategories')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTags')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCustomFields')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->byDefault()->andReturn(true);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($newPage->id)
            ->andReturn($newPage);
    }

    public function testDuplicatePageRelationsContinuesOnPartialFailure()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        // First few succeed
        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->once();

        // This one fails
        $this->pageRepository->shouldReceive('duplicateSeo')
            ->andThrow(new \Exception('SEO duplication failed'));

        // Rest continue
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($newPage);

        // Should not throw - partial duplication is allowed
        $result = $this->service->duplicatePage(1);

        $this->assertNotNull($result);
    }

    public function testDuplicatePageThrowsWhenAllRelationsFail()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        // All relations fail
        $this->pageRepository->shouldReceive('duplicateBlocks')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateMetadata')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSeo')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSettings')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSocial')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateCategories')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateTags')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateCustomFields')
            ->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateAccessRoles')
            ->andThrow(new \Exception('Failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to duplicate any page relations');

        $this->service->duplicatePage(1);
    }

    public function testDuplicatePageLogsErrorsForFailedRelations()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($originalPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')
            ->andThrow(new \Exception('Metadata error'));
        $this->pageRepository->shouldReceive('duplicateSeo')->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($newPage);

        // Capture error_log output
        $errorsCaptured = false;
        set_error_handler(function ($errno, $errstr) use (&$errorsCaptured) {
            if (strpos($errstr, 'Failed to duplicate metadata') !== false) {
                $errorsCaptured = true;
            }
            return true;
        });

        $result = $this->service->duplicatePage(1);

        restore_error_handler();

        $this->assertNotNull($result);
    }

    public function testMergePagesThrowsExceptionForSelfMerge()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Cannot merge a page with itself");

        $this->service->mergePages(1, 1);
    }

    public function testMergePagesThrowsExceptionWhenSourceNotFound()
    {
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn(null);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($this->createMockPage(2));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Source or target page not found");

        $this->service->mergePages(1, 2);
    }

    public function testMergePagesThrowsExceptionWhenTargetNotFound()
    {
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($this->createMockPage(1));

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Source or target page not found");

        $this->service->mergePages(1, 2);
    }

    public function testMergePagesWithAppendStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->setupMergePageExpectations($sourcePage, $targetPage);

        // --- Many-to-many relations (Appendable) ---
        $this->pageRepository->shouldReceive('duplicateCategories')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateTags')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->with(1, 2)->once();

        // --- Blocks (Appended) ---
        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(5);

        // FIX 1: Mock the static call to Block::where() and the chain
        $blockCollection = collect([]);

        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn($blockCollection);

        // Mock Block::create for the loop, expecting 0 calls here since source blocks are empty
        Mockery::mock(Block::class)
            ->shouldReceive('create')
            ->never();

        // --- One-to-one Relations (Merged via mergeSettings) ---
        $this->setupMergeSettingsExpectations();

        // Ensure other one-to-one models are not called, using explicit overloads for safety
        Mockery::mock(PageMetadata::class)->shouldReceive('where')->never();
        Mockery::mock(PageSeo::class)->shouldReceive('where')->never();
        Mockery::mock(PageSocial::class)->shouldReceive('where')->never();

        // --- Custom Fields (Merged) ---
        $this->setupCustomFieldsMergeExpectations();

        //$customFieldCollection = collect([new PageCustomField(['page_id' => 2, 'custom_field_definition_id' => 1, 'value' => 'Custom Field Value'])]);;;

        $customFieldCollection = Mockery::mock(Collection::class)->makePartial();
        $customFieldCollection->items = [new PageCustomField(['page_id' => 2, 'custom_field_definition_id' => 1, 'value' => 'Custom Field Value'])];;

        // --- Deletion and Return ---
        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($targetPage);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn($customFieldCollection)->once();;
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->andReturn($customFieldCollection)->once();

        $customFieldCollection->shouldReceive('pluck')->with('custom_field_definition_id')->andReturn(collect([1]));

        $result = $this->service->mergePages(1, 2, ['strategy' => 'append']);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesWithReplaceStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)->once()->andReturn($targetPage);

        // Transaction mock
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Many-to-many relations appended
        $this->pageRepository->shouldReceive('duplicateCategories')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateTags')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->with(1, 2)->once();

        // Blocks replaced
        $this->blockRepository->shouldReceive('deletePageBlocks')->with(2)->once();
        $this->pageRepository->shouldReceive('duplicateBlocks')->with(1, 2)->once();

        // One-to-one relations replaced
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->with(1, 2)->once();

        // Custom fields merged
        $customFields = collect([]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->with(1)->once()->andReturn($customFields);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->with(2)->once()->andReturn($customFields);

        // Delete source and return target
        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)->once()->andReturn($targetPage);

        $result = $this->service->mergePages(1, 2, ['strategy' => 'replace']);

        $this->assertSame($targetPage, $result);
    }


    public function testMergePagesWithKeepTargetStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->setupMergePageExpectations($sourcePage, $targetPage);

        // Only many-to-many relations are merged
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        // Blocks appended with keep_target
        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(3);

        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));

        // No one-to-one relations replaced with keep_target

        $this->setupCustomFieldsMergeExpectations();

        $customFieldCollection = Mockery::mock(Collection::class)->makePartial();
        $customFieldCollection->items = [new PageCustomField(['page_id' => 2, 'custom_field_definition_id' => 1, 'value' => 'Custom Field Value'])];;

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($targetPage);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn($customFieldCollection)->once();;
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->andReturn($customFieldCollection)->once();

        $customFieldCollection->shouldReceive('pluck')->with('custom_field_definition_id')->andReturn(collect([1]));

        $result = $this->service->mergePages(1, 2, ['strategy' => 'keep_target']);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesDeletesSourceAfterSuccess()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)->twice()->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));

        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->andReturn(collect([]));

        // Assert delete is called
        $this->pageRepository->shouldReceive('delete')
            ->with(1)
            ->once()
            ->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesRollsBackOnFailure()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)
            ->andReturn($sourcePage);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)
            ->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                try {
                    return $callback();
                } catch (\Exception $e) {
                    // Simulate rollback
                    throw $e;
                }
            });

        $this->pageRepository->shouldReceive('duplicateCategories')
            ->andThrow(new \Exception('Merge failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to merge pages');

        $this->service->mergePages(1, 2);
    }

    public function testMergePagesWithContentMerge()
    {
        $sourcePage = $this->createMockPage(1, 'Source Title');
        $sourcePage->meta_description = 'Source description';

        $targetPage = $this->createMockPage(2, 'Target Title');
        $targetPage->meta_description = 'Target description';

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)->twice()->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        // Many-to-many relations
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        // Blocks appended
        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));

        // Settings merged (not replaced in append strategy)
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->with(1)->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->with(2)->andReturn(collect([]));

        // Content merge
        $this->pageRepository->shouldReceive('update')
            ->with(2, Mockery::on(function($updates) {
                return isset($updates['title']) && isset($updates['meta_description']);
            }))
            ->once()
            ->andReturn($targetPage);

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2, [
            'strategy' => 'append',
            'merge_content' => true,
            'append_title' => true,
            'merge_descriptions' => true
        ]);

        $this->assertNotNull($result);
    }

    public function testMergePagesAppendsBlocksWithCorrectOrder()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)->twice()->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        // Target has max order of 3
        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(3);

        // Source has 2 blocks
        $sourceBlocks = collect([
            (object)['type' => 'text', 'data' => ['content' => 'Block 1'], 'order' => 1],
            (object)['type' => 'text', 'data' => ['content' => 'Block 2'], 'order' => 2]
        ]);
        $this->blockRepository->shouldReceive('getBlocksForPage')
            ->with(1)->once()->andReturn($sourceBlocks);

        // Expect blocks created with offset order
        $this->blockRepository->shouldReceive('create')
            ->with(Mockery::on(function($data) {
                static $callCount = 0;
                $callCount++;
                return $data['page_id'] === 2 &&
                    ($data['order'] === 4 && $callCount === 1 ||
                        $data['order'] === 5 && $callCount === 2);
            }))
            ->twice();

        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->andReturn(collect([]));

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2, ['strategy' => 'append']);

        $this->assertNotNull($result);
    }

    public function testMergePagesHandlesEmptyBlocks() //todo
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);

        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(0);

        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));

        $customFieldCollection = Mockery::mock(Collection::class)->makePartial();
        $customFieldCollection->items = [new PageCustomField(['page_id' => 2, 'custom_field_definition_id' => 1, 'value' => 'Custom Field Value'])];;

        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn($customFieldCollection)->once();;
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->andReturn($customFieldCollection)->once();

        $customFieldCollection->shouldReceive('pluck')->with('custom_field_definition_id')->andReturn(collect([1]));

        $this->setupSettingsMergeExpectations();
        $this->setupCustomFieldsMergeExpectations();

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($targetPage);

        $result = $this->service->mergePages(1, 2);

        $this->assertNotNull($result);
    }

    public function testMergePagesDoesNotDuplicateExistingCustomFields()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with(2)->twice()->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));

        // Source has fields 1, 2, 3
        $sourceField1 = new PageCustomField(['custom_field_definition_id' => 1, 'value' => 'val1']);
        $sourceField2 = new PageCustomField(['custom_field_definition_id' => 2, 'value' => 'val2']);
        $sourceField3 = new PageCustomField(['custom_field_definition_id' => 3, 'value' => 'val3']);

        $sourceFields = collect([$sourceField1, $sourceField2, $sourceField3]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->with(1)->once()->andReturn($sourceFields);

        // Target already has fields 1, 2
        $targetField1 = new PageCustomField(['custom_field_definition_id' => 1, 'value' => 'existing1']);
        $targetField2 = new PageCustomField(['custom_field_definition_id' => 2, 'value' => 'existing2']);

        $targetFields = collect([$targetField1, $targetField2]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')
            ->with(2)->once()->andReturn($targetFields);

        // Only field 3 should be created
        $this->customFieldRepository->shouldReceive('create')
            ->with(Mockery::on(function($data) {
                return $data['custom_field_definition_id'] === 3 && $data['page_id'] === 2;
            }))
            ->once();

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertSame($targetPage, $result);
    }


    private function createQueryMock()
    {
        return Mockery::mock(QueryBuilder::class);
    }

    private function setupMergePageExpectations(Page $sourcePage, Page $targetPage): void
    {
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($sourcePage->id)
            ->andReturn($sourcePage);

        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($targetPage->id)
            ->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());
    }

    private function setupSettingsMergeExpectations(): void
    {
        $pageSettings = Mockery::mock(PageSettings::class);

        // Mock PageSettings model methods
        $pageSettings->shouldReceive('where')
            ->with('page_id', Mockery::any())
            ->andReturnSelf();
        $pageSettings->shouldReceive('first')
            ->andReturn(null);
    }

    private function setupCustomFieldsMergeExpectations(): void
    {
        $pageCustomField = Mockery::mock(PageCustomField::class);
        $collection = Mockery::mock(Collection::class);
        $pageCustomField
            ->shouldReceive('toArray')
            ->andReturn(['id' => 1, 'page_id' => 123]);

        $pageCustomField
            ->shouldReceive('where')
            ->with('page_id', Mockery::any())
            ->andReturn($collection);

        $collection
            ->shouldReceive('pluck')
            ->with('custom_field_definition_id')
            ->andReturn($collection);

        $collection
            ->shouldReceive('toArray')
            ->andReturn([$pageCustomField->toArray()]);
    }

    private function setupReplaceOneToOneExpectations(): void
    {
        // -----------------------------------------------------------
        // Helper to mock the deletion chain (where(id)->delete())
        // -----------------------------------------------------------
        $mockDeleteChain = function (string $modelClass) {
            // 1. Create a mock object for the method chain (where->delete)
            $chainMock = Mockery::mock();
            $chainMock->shouldReceive('delete')->once();

            // 2. Mock the static call to Model::where() to return the chain
            Mockery::mock($modelClass)
                ->shouldReceive('where')
                ->with('page_id', 2)
                ->once()
                ->andReturn($chainMock);
        };

        // -----------------------------------------------------------
        // Mock model deletions using the helper
        // -----------------------------------------------------------
        $mockDeleteChain(\App\Models\PageMetadata::class);
        $mockDeleteChain(\App\Models\PageSeo::class);
        $mockDeleteChain(\App\Models\PageSettings::class);
        $mockDeleteChain(\App\Models\PageSocial::class);

        // Mock repository duplications (These are correct)
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->with(1, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->with(1, 2)->once();
    }

    private function setupMergeSettingsExpectations(): void
    {
        // Mock PageSettings model methods for mergeSettings logic
        $sourceSettingsMock = Mockery::mock();
        $sourceSettingsMock->shouldReceive('first')->andReturn(null);

        $targetSettingsMock = Mockery::mock();
        $targetSettingsMock->shouldReceive('first')->andReturn(null);

        // Use 'overload' alias for static Eloquent methods
        Mockery::mock(PageSettings::class)
            ->shouldReceive('where')
            ->with('page_id', 1)
            ->andReturn($sourceSettingsMock);

        Mockery::mock(PageSettings::class)
            ->shouldReceive('where')
            ->with('page_id', 2)
            ->andReturn($targetSettingsMock);

        // Mock the update call that happens in mergeSettings() when data is present
        Mockery::mock(PageSettings::class)
            ->shouldReceive('update')
            ->byDefault()
            ->andReturn(1);
    }

}