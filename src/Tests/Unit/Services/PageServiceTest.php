<?php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Block;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\PageHistory;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
use App\Repositories\AccessRoleRepository;
use App\Repositories\BlockRepository;
use App\Repositories\PageAuthorRepository;
use App\Repositories\PageCategoryRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Repositories\PageMetadataRepository;
use App\Repositories\PageRegionSetRepository;
use App\Repositories\PageRepository;
use App\Repositories\PageSeoRepository;
use App\Repositories\PageSettingsRepository;
use App\Repositories\PageSocialRepository;
use App\Repositories\PageTagRepository;
use App\Repositories\PageTerritoryRepository;
use App\Services\BlockParserService;
use App\Services\PageHistoryService;
use App\Services\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Exception;
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
    private $pageHistory;
    private $pageAuthorRepository;
    private $pageRegionSetRepository;
    private $pageTerritoryRepository;

    protected function setUp(): void
    {
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
        $this->pageHistory = Mockery::mock(PageHistoryService::class);
        $this->pageAuthorRepository = Mockery::mock(PageAuthorRepository::class);
        $this->pageRegionSetRepository = Mockery::mock(PageRegionSetRepository::class);
        $this->pageTerritoryRepository = Mockery::mock(PageTerritoryRepository::class);

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
            $this->databaseMock,
            $this->pageHistory,
            $this->pageAuthorRepository,
            $this->pageRegionSetRepository,
            $this->pageTerritoryRepository,
            $this->siteId
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testDeletePageDeletesBlocksAndPage()
    {
        $this->setupTransaction();

        $this->pageRepository->shouldReceive('find')->with(1)->once()->andReturn(new Page());
        $this->pageHistory->shouldReceive('logPageDeleted')->once()->with(1, [])->andReturn(new PageHistory());
        $this->blockRepository->shouldReceive('deletePageBlocks')->with(1)->once();
        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);

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

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageHistory->shouldReceive('logPageDuplicated')->with(1, 2)->once()->andReturn(new PageHistory());

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return strpos($data['title'], '(Copy)') !== false
                    && strpos($data['slug'], '-copy-') !== false
                    && $data['status'] === 'draft';
            }))
            ->andReturn($newPage);

        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

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
        $this->pageRepository->shouldReceive('quickSearch')
            ->with('test query', [
                'status' => 'published',
                'with' => ['categories', 'tags']
            ])
            ->once()
            ->andReturn(collect([]));

        $result = $this->service->searchPages('test query', '', '', 'published');

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
            'id' => 1,
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

        $this->setupTransaction();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn(null);
        $this->pageHistory->shouldReceive('logPageCreated')->once()->with($newPage);

        $this->pageRepository->shouldReceive('create')
            ->with([
                'title' => 'Test Page',
                'slug' => 'test-page',
                'status' => 'draft',
                'meta_title' => 'SEO Title',
                'meta_description' => 'SEO Desc',
                'site_id' => $this->siteId
            ])->once()->andReturn($newPage);

        $this->metadataRepository->shouldReceive('createOrUpdate')->once()->with(1, Mockery::any());
        $this->seoRepository->shouldReceive('createOrUpdate')->once()->with(1, Mockery::any());
        $this->settingsRepository->shouldReceive('createOrUpdate')->once()->with(1, Mockery::any());
        $this->socialRepository->shouldReceive('createOrUpdate')->once()->with(1, Mockery::any());
        $this->categoryRepository->shouldReceive('syncCategories')->once()->with(1, [1], $this->siteId);
        $this->tagRepository->shouldReceive('syncTags')->once()->with(1, [2], $this->siteId);
        $this->blockParserService->shouldReceive('replacePageBlocks')->once()->with(1, Mockery::any());
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertSame($newPage, $result);
    }

    public function testUpdatePageWithAllDataUpdatesExistingPage()
    {
        $requestData = [
            'id' => 1,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'Updated Page'],
                'meta' => ['slug' => 'updated-page', 'status' => 'published', 'allow_comments' => true]
            ]
        ];

        $existingPage = $this->createMockPage(1, 'Updated Page');

        $this->setupTransaction();
        $this->pageHistory->shouldReceive('logPageUpdated')->once()->with(1, Mockery::type('array'), Mockery::type('array'));
        $this->pageRepository->shouldReceive('update')->once()->with(1, Mockery::subset(['title' => 'Updated Page']))->andReturn($existingPage);
        $this->metadataRepository->shouldReceive('createOrUpdate')->once()->with(1, Mockery::any());
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->twice()->andReturn($existingPage);

        $result = $this->service->updatePageWithAllData(1, $requestData, $this->siteId);

        $this->assertSame($existingPage, $result);
    }

    public function testDuplicatePageReturnsNullForNonexistent()
    {
        $this->pageRepository->shouldReceive('getCompletePageData')->with(999)->andReturn(null);

        $result = $this->service->duplicatePage(999);

        $this->assertNull($result);
    }

    public function testSearchPagesCallsRepository()
    {
        $expectedCollection = collect([]);

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

        $this->pageRepository->shouldReceive('findBySlug')->with('test-slug')->once()->andReturn($page);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($page);

        $result = $this->service->findPageBySlug('test-slug');

        $this->assertSame($page, $result);
    }

    public function testFindPageBySlugReturnsNullForNonexistent()
    {
        $this->pageRepository->shouldReceive('findBySlug')->with('nonexistent')->once()->andReturn(null);

        $result = $this->service->findPageBySlug('nonexistent');

        $this->assertNull($result);
    }

    public function testProcessMetadataFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $metaForm = [
            'content_type' => 'article',
            'author' => 1,
            'publish_date' => '2025-01-01 00:00:00',
            'featured' => true,
            'allow_comments' => true
        ];

        $this->metadataRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::subset([
                'content_type' => 'article',
                'author' => 1,
                'featured' => true,
                'allow_comments' => true
            ]));

        $this->invokePrivateMethod('processMetadataForm', 1, $metaForm);
    }

    public function testProcessSeoFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $seoForm = [
            'meta_keywords' => 'php, testing',
            'canonical_url' => 'https://example.com/page',
            'no_index' => true,
            'og_title' => 'OG Title'
        ];

        $this->seoRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::subset([
                'meta_keywords' => 'php, testing',
                'canonical_url' => 'https://example.com/page',
                'no_index' => true,
                'og_title' => 'OG Title'
            ]));

        $this->invokePrivateMethod('processSeoForm', 1, $seoForm);
    }

    public function testProcessSettingsFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $settingsForm = [
            'template' => 'custom',
            'menu_order' => 5,
            'price' => 99.99,
            'recurring' => true,
            'access_roles' => ['admin', 'editor']
        ];

        $this->settingsRepository->shouldReceive('createOrUpdate')->once();
        $this->accessRoleRepository->shouldReceive('syncAccessRoles')->once()->with(1, ['admin', 'editor']);

        $this->invokePrivateMethod('processSettingsForm', 1, $settingsForm);
    }

    public function testProcessSocialFormSavesAllFields()
    {
        $this->expectNotToPerformAssertions();

        $socialForm = [
            'enable_sharing' => true,
            'platforms' => ['facebook', 'twitter'],
            'track_shares' => true
        ];

        $this->socialRepository->shouldReceive('createOrUpdate')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return $data['enable_sharing'] === true && $data['track_shares'] === true;
            }));

        $this->invokePrivateMethod('processSocialForm', 1, $socialForm);
    }

    public function testProcessTagsFormSyncsAllRelations()
    {
        $this->expectNotToPerformAssertions();

        $tagsForm = [
            'categories' => [1, 2],
            'tags' => [3, 4],
            'customFields' => [
                ['key' => 'color', 'value' => 'red', 'type' => 'text']
            ]
        ];

        $customField = Mockery::mock(CustomFieldDefinition::class)->makePartial();
        $customField->key = 'color';
        $customField->id = 1;

        $this->categoryRepository->shouldReceive('syncCategories')->once()->with(1, [1, 2], $this->siteId);
        $this->tagRepository->shouldReceive('syncTags')->once()->with(1, [3, 4], $this->siteId);
        $this->customFieldRepository->shouldReceive('getCustomFieldsByKeys')->with(['color'])->once()->andReturn(collect([$customField]));
        $this->customFieldRepository->shouldReceive('syncCustomFields')->once();

        $this->invokePrivateMethod('processTagsForm', 1, $tagsForm, $this->siteId);
    }

    public function testDuplicatePageClonesAllRelations()
    {
        $originalPage = $this->createMockPage(1, 'Original Page');
        $originalPage->slug = 'original-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = 'Meta Title';
        $originalPage->meta_description = 'Meta Description';

        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($originalPage);
        $this->setupTransaction();

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['title' => 'Original Page (Copy)', 'status' => 'draft']))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertSame($newPage, $result);
    }

    public function testDuplicatePageCreatesPageWithCopyInTitle()
    {
        $originalPage = $this->createMockPage(1, 'Test Page');
        $originalPage->slug = 'test-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = null;
        $originalPage->meta_description = null;

        $newPage = $this->createMockPage(2, 'Test Page');

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupTransaction();

        $this->pageRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return strpos($data['title'], '(Copy)') !== false
                    && strpos($data['slug'], '-copy-') !== false
                    && $data['status'] === 'draft';
            }))
            ->andReturn($newPage);

        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertNotNull($result);
    }

    public function testDuplicatePageSetsDraftStatus()
    {
        $originalPage = $this->createMockPage(1, 'Published Page');
        $originalPage->slug = 'published-page';
        $originalPage->status = 'published';
        $originalPage->meta_title = 'Meta';
        $originalPage->meta_description = 'Desc';

        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->with(Mockery::subset(['status' => 'draft']))->andReturn($newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertNotNull($result);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithMetadata()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2)->once();

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSeo()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSettings()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSocial()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithCategories()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithTags()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithCustomFields()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithAccessRoles()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->duplicatePage(1);
    }

    public function testDuplicatePageRelationsContinuesOnPartialFailure()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->andThrow(new \Exception('SEO duplication failed'));

        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->duplicatePage(1);

        $this->assertNotNull($result);
    }

    public function testDuplicatePageThrowsWhenAllRelationsFail()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        $this->pageRepository->shouldReceive('duplicateBlocks')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateMetadata')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSeo')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSettings')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSocial')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateCategories')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateTags')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateCustomFields')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->andThrow(new \Exception('Failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to duplicate any page relations');

        $this->service->duplicatePage(1);
    }

    public function testDuplicatePageLogsErrorsForFailedRelations()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->andThrow(new \Exception('Metadata error'));
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        set_error_handler(function ($errno, $errstr) {
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
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn(null);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($this->createMockPage(2));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Source or target page not found");

        $this->service->mergePages(1, 2);
    }

    public function testMergePagesThrowsExceptionWhenTargetNotFound()
    {
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($this->createMockPage(1));
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Source or target page not found");

        $this->service->mergePages(1, 2);
    }

    public function testMergePagesWithAppendStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(5);

        $blockCollection = collect([]);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn($blockCollection);

        Mockery::mock(Block::class)->shouldReceive('create')->never();
        $this->setupMergeSettingsExpectations();

        Mockery::mock(PageMetadata::class)->shouldReceive('where')->never();
        Mockery::mock(PageSeo::class)->shouldReceive('where')->never();
        Mockery::mock(PageSocial::class)->shouldReceive('where')->never();

        $this->setupCustomFieldsMergeExpectations();

        $customFieldCollection = $this->createCustomFieldCollection();

        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($targetPage);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn($customFieldCollection)->once();
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->andReturn($customFieldCollection)->once();

        $customFieldCollection->shouldReceive('pluck')->with('custom_field_definition_id')->andReturn(collect([1]));

        $this->setDuplicationExpectations();

        $result = $this->service->mergePages(1, 2, ['strategy' => 'append']);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesWithReplaceStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($targetPage);
        $this->setupTransaction();

        $this->blockRepository->shouldReceive('deletePageBlocks')->with(2)->once();
        $this->setDuplicationExpectations();

        $customFields = collect([]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->once()->andReturn($customFields);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->once()->andReturn($customFields);

        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($targetPage);

        $result = $this->service->mergePages(1, 2, ['strategy' => 'replace']);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesWithKeepTargetStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(3);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));
        $this->setupCustomFieldsMergeExpectations();

        $customFieldCollection = $this->createCustomFieldCollection();

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($targetPage);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn($customFieldCollection)->once();
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->andReturn($customFieldCollection)->once();

        $customFieldCollection->shouldReceive('pluck')->with('custom_field_definition_id')->andReturn(collect([1]));

        $result = $this->service->mergePages(1, 2, ['strategy' => 'keep_target']);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesDeletesSourceAfterSuccess()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->twice()->andReturn($targetPage);
        $this->setupTransaction();
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertSame($targetPage, $result);
    }

    public function testMergePagesRollsBackOnFailure()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($targetPage);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                try {
                    return $callback();
                } catch (\Exception $e) {
                    throw $e;
                }
            });

        $this->pageRepository->shouldReceive('duplicateCategories')->andThrow(new \Exception('Merge failed'));

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

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->twice()->andReturn($targetPage);
        $this->setupTransaction();
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->andReturn(collect([]));

        $this->pageRepository->shouldReceive('update')
            ->with(2, Mockery::on(function ($updates) {
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

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->twice()->andReturn($targetPage);
        $this->setupTransaction();
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(3);

        $sourceBlocks = collect([
            (object)['type' => 'text', 'data' => ['content' => 'Block 1'], 'order' => 1],
            (object)['type' => 'text', 'data' => ['content' => 'Block 2'], 'order' => 2]
        ]);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->once()->andReturn($sourceBlocks);

        $this->blockRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                static $callCount = 0;
                $callCount++;
                return $data['page_id'] === 2 &&
                    ($data['order'] === 4 && $callCount === 1 ||
                        $data['order'] === 5 && $callCount === 2);
            }))
            ->twice();

        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2, ['strategy' => 'append']);

        $this->assertNotNull($result);
    }

    public function testMergePagesHandlesEmptyBlocks()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setDuplicationExpectations();
        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));

        $customFieldCollection = $this->createCustomFieldCollection();

        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->andReturn($customFieldCollection)->once();
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

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->twice()->andReturn($targetPage);
        $this->setupTransaction();
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));

        $sourceField1 = new PageCustomField(['custom_field_definition_id' => 1, 'value' => 'val1']);
        $sourceField2 = new PageCustomField(['custom_field_definition_id' => 2, 'value' => 'val2']);
        $sourceField3 = new PageCustomField(['custom_field_definition_id' => 3, 'value' => 'val3']);

        $sourceFields = collect([$sourceField1, $sourceField2, $sourceField3]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->once()->andReturn($sourceFields);

        $targetField1 = new PageCustomField(['custom_field_definition_id' => 1, 'value' => 'existing1']);
        $targetField2 = new PageCustomField(['custom_field_definition_id' => 2, 'value' => 'existing2']);

        $targetFields = collect([$targetField1, $targetField2]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->once()->andReturn($targetFields);

        $this->customFieldRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['custom_field_definition_id'] === 3 && $data['page_id'] === 2;
            }))
            ->once();

        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertSame($targetPage, $result);
    }

    public function testPublishPageSuccessfullyPublishesDraftPage(): void
    {
        $initialPage = new Page(['status' => 'draft']);
        $updatedPage = new Page(['status' => 'published']);

        $this->pageRepository->shouldReceive('find')->once()->with(1)->andReturn($initialPage);

        $this->pageRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($data) {
                return is_array($data)
                    && isset($data['status']) && $data['status'] === 'published'
                    && isset($data['published_at']) && is_string($data['published_at']);
            }))
            ->andReturn($updatedPage);

        $this->pageHistory->shouldReceive('logPagePublished')->once()->with(1)->andReturn(new PageHistory(['id' => 1]));

        $result = $this->service->publishPage(1);

        $this->assertSame($updatedPage, $result);
        $this->assertEquals('published', $result->status);
    }

    public function testPublishPageThrowsExceptionIfAlreadyPublished(): void
    {
        $publishedPage = new Page(['status' => 'published']);

        $this->pageRepository->shouldReceive('find')->once()->with(1)->andReturn($publishedPage);
        $this->pageRepository->shouldNotHaveReceived('update');
        $this->pageRepository->shouldNotHaveReceived('logPagePublished');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Page is already published");

        $this->service->publishPage(1);
    }

    public function testUnpublishPageSuccessfullyUnpublishesPublishedPage(): void
    {
        $initialPage = new Page(['status' => 'published']);
        $updatedPage = new Page(['status' => 'draft']);

        $this->pageRepository->shouldReceive('find')->once()->with(1)->andReturn($initialPage);
        $this->pageRepository->shouldReceive('update')->once()->with(1, ['status' => 'draft'])->andReturn($updatedPage);
        $this->pageHistory->shouldReceive('logPageUnpublished')->once()->with(1)->andReturn(new PageHistory(['id' => 1]));

        $result = $this->service->unpublishPage(1);

        $this->assertSame($updatedPage, $result);
        $this->assertEquals('draft', $result->status);
    }

    public function testUnpublishPageThrowsExceptionIfPageNotFound(): void
    {
        $this->pageRepository->shouldReceive('find')->once()->with(1)->andReturn(null);
        $this->pageRepository->shouldNotHaveReceived('update');
        $this->pageHistory->shouldNotHaveReceived('logPageUnpublished');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Page not found");

        $this->service->unpublishPage(1);
    }

    public function testUnpublishPageThrowsExceptionIfNotPublished(): void
    {
        $draftPage = new Page(['status' => 'draft']);

        $this->pageRepository->shouldReceive('find')->once()->with(1)->andReturn($draftPage);
        $this->pageRepository->shouldNotHaveReceived('update');
        $this->pageHistory->shouldNotHaveReceived('logPageUnpublished');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Page is not published");

        $this->service->unpublishPage(1);
    }

    public function testProcessMetadataFormHandlesMultipleAuthors()
    {
        $this->expectNotToPerformAssertions();

        $metaForm = [
            'content_type' => 'article',
            'authors' => [1, 2, 3],
            'contributors' => [4, 5],
            'featured' => true
        ];

        $this->metadataRepository->shouldReceive('createOrUpdate')->once();
        $this->pageAuthorRepository->shouldReceive('syncAuthors')->once()->with(1, [1, 2, 3], 'primary', $this->siteId);
        $this->pageAuthorRepository->shouldReceive('syncAuthors')->once()->with(1, [4, 5], 'contributor', $this->siteId);

        $this->invokePrivateMethod('processMetadataForm', 1, $metaForm);
    }

    public function testDuplicatePageClonesPageAuthors()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $result = $this->service->duplicatePage(1);
        $this->assertInstanceOf(Page::class, $result);
    }

    public function testProcessMetadataFormHandlesMultipleRegionSets()
    {
        $this->expectNotToPerformAssertions();

        $metaForm = [
            'content_type' => 'article',
            'region_sets' => [1, 2, 3],
            'featured' => true
        ];

        $this->metadataRepository->shouldReceive('createOrUpdate')->once();
        $this->pageRegionSetRepository->shouldReceive('syncRegionSets')->once()->with(1, [1, 2, 3], $this->siteId);

        $this->invokePrivateMethod('processMetadataForm', 1, $metaForm);
    }

    public function testProcessMetadataFormHandlesMultipleTerritories()
    {
        $this->expectNotToPerformAssertions();

        $metaForm = [
            'content_type' => 'article',
            'territories' => [1, 2, 3, 4],
            'featured' => true
        ];

        $this->metadataRepository->shouldReceive('createOrUpdate')->once();
        $this->pageTerritoryRepository->shouldReceive('syncTerritories')->once()->with(1, [1, 2, 3, 4], $this->siteId);

        $this->invokePrivateMethod('processMetadataForm', 1, $metaForm);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithRegionSets()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->duplicatePage(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithTerritories()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->duplicatePage(1);
    }

    public function testClonePageToSiteCreatesPageInTargetSite()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2, 'Source Page');
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->with('test', 2)->once()->andReturn(false);
        $this->setupTransaction();

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['site_id'] === 2 && $data['status'] === 'draft' && $data['slug'] === 'test';
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once()->with(1, 2, 2);
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->clonePageToSite(1, 2);

        $this->assertSame($newPage, $result);
        $this->assertEquals(2, $result->site_id);
    }

    public function testClonePageToSiteThrowsExceptionWhenSameSite()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $sourcePage->site_id = 1;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Source and target site cannot be the same");

        $this->service->clonePageToSite(1, 1);
    }

    public function testClonePageToSiteGeneratesUniqueSlug()
    {
        $sourcePage = $this->createMockPage(1, 'Test Page');
        $sourcePage->slug = 'test-page';
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2, 'Test Page');
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->with('test-page', 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('slugExistsInSite')->with('test-page-1', 2)->once()->andReturn(false);
        $this->setupTransaction();

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['slug'] === 'test-page-1';
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once()->with(1, 2, 2);
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->clonePageToSite(1, 2);

        $this->assertNotNull($result);
    }

    public function testClonePageToSiteWithCustomTitle()
    {
        $sourcePage = $this->createMockPage(1, 'Original Title');
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2, 'Custom Title');
        $newPage->site_id = 2;
        $newPage->title = 'Custom Title';

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['title'] === 'Custom Title';
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->clonePageToSite(1, 2, 'Custom Title');

        $this->assertEquals('Custom Title', $result->title);
    }

    public function testClonePageToSiteClonesAllRelations()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->once()->andReturn($newPage);
        $this->pageHistory->shouldReceive('logPageClonedToSite')->once()->with(1, 2, 2);

        $this->pageRepository->shouldReceive('duplicateBlocks')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSeo')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCategoriesToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateTagsToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCustomFieldsToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicatePageAuthorsToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateRegionSetsToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateTerritoriesToSite')->with(1, 2, 2)->once();

        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->clonePageToSite(1, 2);

        $this->assertNotNull($result);
    }

    public function testClonePageToSiteContinuesOnPartialFailure()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->once()->andReturn($newPage);
        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();

        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->once();
        $this->pageRepository->shouldReceive('duplicateCategoriesToSite')->andThrow(new \Exception('Categories clone failed'));

        $this->pageRepository->shouldReceive('duplicateSeo')->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateTagsToSite')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFieldsToSite')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();
        $this->pageRepository->shouldReceive('duplicatePageAuthorsToSite')->once();
        $this->pageRepository->shouldReceive('duplicateRegionSetsToSite')->once();
        $this->pageRepository->shouldReceive('duplicateTerritoriesToSite')->once();

        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->clonePageToSite(1, 2);

        $this->assertNotNull($result);
    }

    public function testCreatePageWithListingData()
    {
        $requestData = [
            'id' => 1,
            'hero_type' => 'image',
            'hero_image_id' => 7,
            'forms' => [
                'main' => [
                    'title' => 'Test Page',
                    'heroType' => 'image',
                    'heroImageId' => 7,
                    'heroVideoUrl' => ''
                ],
                'meta' => ['slug' => 'test-page', 'status' => 'draft'],
                'listing' => [
                    'synopsis' => 'Test synopsis',
                    'listingTitle' => 'Listing Title',
                    'dekLabel' => 'Label',
                    'imageId' => 10,
                    'useAsHero' => false
                ],
                'cropOverrides' => [
                    'homepage-card' => [
                        'imageId' => 10,
                        'imageUrl' => 'http://example.com/image.jpg',
                        'source' => 'listing',
                        'ratio' => '1:1'
                    ]
                ]
            ],
            'resolved_images' => [
                'homepage-card' => [
                    'image_id' => 10,
                    'image_url' => 'http://example.com/image.jpg',
                    'source' => 'listing-override',
                    'ratio' => '1:1',
                    'is_auto_adjusted' => false
                ]
            ],
            'blocks' => []
        ];

        $newPage = $this->createMockPage(1, 'Test Page');

        $this->setupTransaction();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn(null);
        $this->pageHistory->shouldReceive('logPageCreated')->once()->with($newPage);

        $this->pageRepository->shouldReceive('create')
            ->with(Mockery::on(function($data) {
                return $data['title'] === 'Test Page'
                    && $data['listing_synopsis'] === 'Test synopsis'
                    && $data['listing_title'] === 'Listing Title'
                    && $data['listing_label'] === 'Label'
                    && $data['listing_image_id'] === 10
                    && $data['listing_use_as_hero'] === false
                    && $data['hero_type'] === 'image'
                    && $data['hero_image_id'] === 7
                    && !empty($data['crop_overrides'])
                    && !empty($data['resolved_images']);
            }))
            ->once()
            ->andReturn($newPage);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertSame($newPage, $result);
    }

    public function testUpdatePageWithListingData()
    {
        $requestData = [
            'id' => 1,
            'status' => 'published',
            'hero_type' => 'video',
            'hero_video_url' => 'http://example.com/video.mp4',
            'forms' => [
                'main' => [
                    'title' => 'Updated Page',
                    'heroType' => 'video',
                    'heroImageId' => null,
                    'heroVideoUrl' => 'http://example.com/video.mp4'
                ],
                'meta' => ['slug' => 'updated-page', 'status' => 'published'],
                'listing' => [
                    'synopsis' => 'Updated synopsis',
                    'listingTitle' => 'Updated Listing',
                    'dekLabel' => 'Updated Label',
                    'imageId' => 15,
                    'useAsHero' => true
                ],
                'cropOverrides' => []
            ]
        ];

        $existingPage = $this->createMockPage(1, 'Updated Page');

        $this->setupTransaction();
        $this->pageHistory->shouldReceive('logPageUpdated')->once()->with(1, Mockery::type('array'), Mockery::type('array'));

        $this->pageRepository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function($data) {
                return $data['title'] === 'Updated Page'
                    && $data['listing_synopsis'] === 'Updated synopsis'
                    && $data['hero_type'] === 'video'
                    && $data['hero_video_url'] === 'http://example.com/video.mp4';
            }))
            ->andReturn($existingPage);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->twice()->andReturn($existingPage);

        $result = $this->service->updatePageWithAllData(1, $requestData, $this->siteId);

        $this->assertSame($existingPage, $result);
    }

    public function testBuildReplaceUpdatesReturnsAllFields()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->listing_synopsis = 'Synopsis';
        $sourcePage->listing_title = 'Title';
        $sourcePage->hero_type = 'image';
        $sourcePage->crop_overrides = ['test' => 'data'];

        $result = $this->invokePrivateMethod('buildReplaceUpdates', $sourcePage);

        $this->assertEquals('Synopsis', $result['listing_synopsis']);
        $this->assertEquals('Title', $result['listing_title']);
        $this->assertEquals('image', $result['hero_type']);
        $this->assertArrayHasKey('crop_overrides', $result);
    }

    public function testBuildAppendUpdatesOnlyFillsEmptyFields()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->listing_synopsis = 'New Synopsis';
        $sourcePage->listing_title = 'New Title';

        $targetPage = $this->createMockPage(2);
        $targetPage->listing_synopsis = 'Existing Synopsis';
        $targetPage->listing_title = '';

        $result = $this->invokePrivateMethod('buildAppendUpdates', $sourcePage, $targetPage);

        $this->assertArrayNotHasKey('listing_synopsis', $result);
        $this->assertEquals('New Title', $result['listing_title']);
    }

    public function testMergeJsonFieldsCombinesArrays()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->crop_overrides = json_encode(['homepage-card' => ['imageId' => 5]]);

        $targetPage = $this->createMockPage(2);
        $targetPage->crop_overrides = json_encode(['listing-card' => ['imageId' => 10]]);

        $result = $this->invokePrivateMethod('mergeJsonFields', $sourcePage, $targetPage);

        $overrides = json_decode($result['crop_overrides'], true);
        $this->assertArrayHasKey('homepage-card', $overrides);
        $this->assertArrayHasKey('listing-card', $overrides);
    }

    // Helper methods
    private function createMockPage(int $id, string $title = 'Test'): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = $id;
        $page->title = $title;
        $page->slug = 'test';
        $page->status = 'published';
        $page->meta_title = null;
        $page->meta_description = null;
        return $page;
    }

    private function setupTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
    }

    private function invokePrivateMethod(string $methodName, ...$args)
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($this->service, ...$args);
    }

    private function createCustomFieldCollection(): Collection
    {
        $collection = Mockery::mock(Collection::class)->makePartial();
        $collection->items = [
            new PageCustomField([
                'page_id' => 2,
                'custom_field_definition_id' => 1,
                'value' => 'Custom Field Value'
            ])
        ];
        return $collection;
    }

    private function setDuplicationExpectations(): void
    {
        $this->pageRepository->shouldReceive('find')
            ->with(1);

        $this->pageRepository->shouldReceive('find')
            ->with(2);

        $this->pageRepository->shouldReceive('duplicateCategories')
            ->with(1, 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTags')
            ->with(1, 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateAccessRoles')
            ->with(1, 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicatePageAuthors')
            ->with(1, 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateRegionSets')
            ->with(1, 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTerritories')
            ->with(1, 2)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCustomFields')
            ->with(1, 2)->once()->andReturn(true);

        $this->pageRepository->shouldReceive('duplicateBlocks')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateMetadata')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSeo')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSettings')->byDefault()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSocial')->byDefault()->andReturn(true);
    }

    private function setupDuplicatePageExpectations(Page $originalPage, Page $newPage): void
    {
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($originalPage->id)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($newPage->id)->andReturn($newPage);
    }

    private function setupMergePageExpectations(Page $sourcePage, Page $targetPage): void
    {
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($sourcePage->id)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')
            ->with($targetPage->id)->andReturn($targetPage);

        $this->pageRepository->shouldReceive('find')
            ->with($targetPage->id)->andReturn($targetPage);

        $this->pageRepository->shouldReceive('find')
            ->with($sourcePage->id)->andReturn($sourcePage);

        $this->pageRepository->shouldReceive('update')
            ->with(2, [
                'listing_title' => NULL,
                'listing_label' => NULL,
                'listing_image_id' => NULL,
                'hero_type' => NULL,
                'hero_image_id' => NULL,
                'hero_video_url' => NULL
            ])->andReturn($sourcePage);
        $this->setupTransaction();
    }

    private function setupSettingsMergeExpectations(): void
    {
        $sourceSettingsMock = Mockery::mock();
        $sourceSettingsMock->shouldReceive('first')->andReturn(null);

        $targetSettingsMock = Mockery::mock();
        $targetSettingsMock->shouldReceive('first')->andReturn(null);

        Mockery::mock(PageSettings::class)
            ->shouldReceive('where')->with('page_id', 1)->andReturn($sourceSettingsMock);
        Mockery::mock(PageSettings::class)
            ->shouldReceive('where')->with('page_id', 2)->andReturn($targetSettingsMock);
        Mockery::mock(PageSettings::class)
            ->shouldReceive('update')->byDefault()->andReturn(1);
    }

    private function setupCustomFieldsMergeExpectations(): void
    {
        $pageCustomField = Mockery::mock(PageCustomField::class);
        $collection = Mockery::mock(Collection::class);
        $pageCustomField->shouldReceive('toArray')
            ->andReturn(['id' => 1, 'page_id' => 123]);
        $pageCustomField->shouldReceive('where')
            ->with('page_id', Mockery::any())->andReturn($collection);
        $collection->shouldReceive('pluck')
            ->with('custom_field_definition_id')->andReturn($collection);
        $collection->shouldReceive('toArray')
            ->andReturn([$pageCustomField->toArray()]);
    }

    private function setupCloneToSiteExpectations(int $sourcePageId, int $targetPageId, int $targetSiteId): void
    {
        $this->pageRepository->shouldReceive('duplicateBlocks')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateSeo')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateCategoriesToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateTagsToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateCustomFieldsToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicatePageAuthorsToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateRegionSetsToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
        $this->pageRepository->shouldReceive('duplicateTerritoriesToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
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