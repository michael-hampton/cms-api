<?php

namespace App\Tests\Unit\Services;

use App\Enums\PageStatus;
use App\Framework\Database\Database;
use App\Models\CustomFieldDefinition;
use App\Models\Page;
use App\Models\PageHistory;
use App\Repositories\Cms\AccessRoleRepository;
use App\Repositories\Cms\BlockRepository;
use App\Repositories\Cms\PageAuthorRepository;
use App\Repositories\Cms\PageCategoryRepository;
use App\Repositories\Cms\PageCustomFieldRepository;
use App\Repositories\Cms\PageMetadataRepository;
use App\Repositories\Cms\PageProductRepository;
use App\Repositories\Cms\PageRegionSetRepository;
use App\Repositories\Cms\PageRepository;
use App\Repositories\Cms\PageSeoRepository;
use App\Repositories\Cms\PageSettingsRepository;
use App\Repositories\Cms\PageSocialRepository;
use App\Repositories\Cms\PageTagRepository;
use App\Repositories\Cms\PageTerritoryRepository;
use App\Services\Cms\BlockParserService;
use App\Services\Cms\PageHistoryService;
use App\Services\Cms\PageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Exception;
use Mockery;

class PageServiceTest extends FunctionalTestCase
{
    use CreatesTestData, HasSiteHistory;

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
    private $pageProductRepository;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('log_errors', 0);

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->pageProductRepository = Mockery::mock(PageProductRepository::class);
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
            $this->pageProductRepository,
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

        $this->pageRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($existingPage);

        $this->setupTransaction();
        $this->pageHistory->shouldReceive('logPageUpdated')->once()->with(1, Mockery::type('array'), Mockery::type('array'));
        $this->pageRepository->shouldReceive('update')->once()->with(1, Mockery::subset(['title' => 'Updated Page']))->andReturn($existingPage);
        $this->metadataRepository->shouldReceive('createOrUpdate')->once()->with(1, Mockery::any());
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->twice()->andReturn($existingPage);

        $result = $this->service->updatePageWithAllData(1, $requestData, $this->siteId);

        $this->assertSame($existingPage, $result);
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

        $this->pageRepository->expects('find')
            ->with(1)
            ->once()
            ->andReturn($existingPage);

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

    public function testProcessTagsFormSyncsProducts()
    {
        $this->expectNotToPerformAssertions();

        $tagsForm = [
            'categories' => [1, 2],
            'tags' => [3, 4],
            'products' => [5, 6, 7]
        ];

        $this->categoryRepository->shouldReceive('syncCategories')->once()->with(1, [1, 2], $this->siteId);
        $this->tagRepository->shouldReceive('syncTags')->once()->with(1, [3, 4], $this->siteId);
        $this->pageProductRepository->shouldReceive('syncProducts')->once()->with(1, [5, 6, 7], $this->siteId);

        $this->invokePrivateMethod('processTagsForm', 1, $tagsForm, $this->siteId);
    }

    public function testPublishPageWithApprovalRequiredGoesToWaitingApproval()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->requires_approval = true;
        $page->id = 1;
        $page->status = 'draft';

        $this->pageRepository->shouldReceive('find')->with($page->id)->andReturn($page);
        $this->pageRepository->shouldReceive('getCompletePageData')->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPageWaitingApproval')->once();
        $this->pageHistory->shouldReceive('logPageUpdated')->once();

        $requestData = [
            'id' => $page->id,
            'status' => 'published',
            'forms' => ['meta' => ['status' => 'published']],
            'site_id' => $this->siteId
        ];

        $result = $this->service->updatePageWithAllData($page->id, $requestData, $this->siteId);

        $this->assertNotNull($result);
    }

    public function testPublishPageWithoutApprovalRequiredPublishes()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->requires_approval = false;
        $page->id = 1;
        $page->status = 'draft';

        $this->pageRepository->shouldReceive('find')->with($page->id)->andReturn($page);
        $this->pageRepository->shouldReceive('getCompletePageData')->twice()->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPagePublished')->once();

        $requestData = [
            'id' => $page->id,
            'status' => 'published',
            'forms' => ['meta' => ['status' => 'published']],
            'site_id' => $this->siteId
        ];

        $result = $this->service->updatePageWithAllData($page->id, $requestData, $this->siteId);

        $this->assertNotNull($result);
    }

    public function testApprovePageSuccessfully()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->status = 'waiting_approval';
        $page->requires_approval = true;

        $page->shouldReceive('isWaitingApproval')->andReturn(true);
        $page->shouldReceive('approve')->once()->with(1);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPageApproved')->once();
        $this->pageHistory->shouldReceive('logPagePublished')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->once()->andReturn($page);

        $result = $this->service->approvePage(1, 1);

        $this->assertNotNull($result);
    }

    public function testApprovePageThrowsExceptionIfNotWaitingApproval()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->status = 'draft';

        $page->shouldReceive('isWaitingApproval')->andReturn(false);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Page is not waiting for approval");

        $this->service->approvePage(1, 1);
    }

    public function testRejectPageSuccessfully()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->status = 'waiting_approval';

        $page->shouldReceive('isWaitingApproval')->andReturn(true);
        $page->shouldReceive('removeApproval')->once();

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPageRejected')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->once()->andReturn($page);

        $result = $this->service->rejectPage(1, 1, 'Not ready for publishing');

        $this->assertNotNull($result);
    }

    public function testPutPageOnHold()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->status = 'draft';

        $page->shouldReceive('canTransitionTo')->with('on_hold')->andReturn(true);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPagePutOnHold')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->once()->andReturn($page);

        $result = $this->service->putPageOnHold(1, 1, 'Needs more review');

        $this->assertNotNull($result);
    }

    public function testMakePagePrivate()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;
        $page->status = 'draft';

        $page->shouldReceive('canTransitionTo')->with('private')->andReturn(true);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPageMadePrivate')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->once()->andReturn($page);

        $result = $this->service->makePagePrivate(1, 1);

        $this->assertNotNull($result);
    }

    public function testCannotTransitionFromArchivedToPublished()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->status = 'archived';

        $this->assertFalse($page->canTransitionTo('published'));
    }

    public function testCanTransitionFromWaitingApprovalToDraft()
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->status = 'waiting_approval';

        $this->assertTrue($page->canTransitionTo('draft'));
    }

    public function testCreatePageWithRequiresApprovalAndPublishedStatusGoesToWaitingApproval()
    {
        $requestData = [
            'requires_approval' => true,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'published']
            ],
            'blocks' => []
        ];

        $newPage = $this->createMockPage(1, 'New Page');
        $newPage->status = PageStatus::WAITING_APPROVAL->value;

        $this->setupTransaction();
        //$this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn(null);

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['status'] === 'waiting_approval'
                    && $data['requires_approval'] === true;
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageCreated')->once()->with($newPage);
        $this->pageHistory->shouldReceive('logPageWaitingApproval')->once()->with($newPage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertNotNull($result);
    }

    public function testCreatePageWithoutRequiresApprovalPublishes()
    {
        $requestData = [
            'requires_approval' => false,
            'status' => 'published',
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'published']
            ],
            'blocks' => []
        ];

        $newPage = $this->createMockPage(1, 'New Page');
        $newPage->status = PageStatus::PUBLISHED->value;;

        $this->setupTransaction();
        //$this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn(null);

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['status'] === 'published'
                    && $data['requires_approval'] === false;
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageCreated')->once()->with($newPage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertNotNull($result);
    }

    public function testCreatePageWithRequiresApprovalAsDraftStaysDraft()
    {
        $requestData = [
            'requires_approval' => true,
            'status' => 'draft',
            'forms' => [
                'main' => ['title' => 'New Page'],
                'meta' => ['slug' => 'new-page', 'status' => 'draft']
            ],
            'blocks' => []
        ];

        $newPage = $this->createMockPage(1, 'New Page');
        $newPage->status = PageStatus::DRAFT->value;

        $this->setupTransaction();
        //$this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn(null);

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return $data['status'] === 'draft'
                    && $data['requires_approval'] === true;
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageCreated')->once()->with($newPage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertNotNull($result);
    }

    public function testCreatePageExtractsRequiresApprovalFromRequest()
    {
        $requestData = [
            'requires_approval' => true,
            'forms' => [
                'main' => ['title' => 'Test Page'],
                'meta' => ['slug' => 'test-page', 'status' => 'draft']
            ],
            'blocks' => []
        ];

        $newPage = $this->createMockPage(1, 'Test Page');

        $this->setupTransaction();
        //$this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn(null);

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function($data) {
                return isset($data['requires_approval'])
                    && $data['requires_approval'] === true;
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageCreated')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertNotNull($result);
    }

    public function testMakePageInternal()
    {
        $page = $this->createPage(['status' => 'draft']);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('update')->once()->andReturn($page);
        $this->pageHistory->shouldReceive('logPageMadeInternal')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->once()->andReturn($page);

        $result = $this->service->makePageInternal(1, 1);

        $this->assertNotNull($result);
    }

    public function testCreatePageWithZones()
    {
        $requestData = [
            'forms' => [
                'main' => ['title' => 'Page with Zones'],
                'meta' => ['slug' => 'page-with-zones', 'status' => 'draft']
            ],
            'zones' => [
                [
                    'id' => 'zone-a',
                    'name' => 'Main Zone',
                    'columns' => 2,
                    'blocks' => [[1], [2]],
                    'options' => [
                        'background' => 'muted',
                        'padding' => 'large',
                        'width' => 'contained'
                    ],
                    'sortOrder' => 0
                ]
            ],
            'blocks' => []
        ];

        $newPage = $this->createMockPage(1, 'Page with Zones');

        $this->setupTransaction();
        $this->pageHistory->shouldReceive('logPageCreated')->once()->with($newPage);

        $this->pageRepository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                return $data['title'] === 'Page with Zones'
                    && isset($data['zones'])
                    && is_string($data['zones']);
            }))
            ->once()
            ->andReturn($newPage);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($newPage);

        $result = $this->service->createPageWithAllData($requestData, $this->siteId);

        $this->assertSame($newPage, $result);
    }
}