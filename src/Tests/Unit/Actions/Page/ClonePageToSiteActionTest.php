<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\ClonePageToSite;
use App\Framework\Database\Database;
use App\Models\Page;
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
use App\Services\Cms\ClonePermissionChecker;
use App\Services\Cms\PageHistoryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class ClonePageToSiteActionTest extends FunctionalTestCase
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
    private $clonePermissionChecker;

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
        $this->clonePermissionChecker = Mockery::mock(ClonePermissionChecker::class);

        $this->service = new ClonePageToSite(
            $this->pageRepository,
           $this->databaseMock,
            $this->pageHistory,
            $this->clonePermissionChecker
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1, 2);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);
    }

    public function testClonePageToSiteThrowsExceptionWhenSameSite()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $sourcePage->site_id = 1;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Source and target site cannot be the same");

        $this->service->handle(1, 1);
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
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['slug'] === 'test-page-1';
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once()->with(1, 2, 2);
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1, 2);

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
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

        $this->pageRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['title'] === 'Custom Title';
            }))
            ->andReturn($newPage);

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1, 2, 'Custom Title');

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);
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
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

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
        $this->pageRepository->shouldReceive('duplicateProductsToSite')->with(1, 2, 2)->once();

        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1, 2);

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
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

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
        $this->pageRepository->shouldReceive('duplicateProductsToSite')->once();

        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1, 2);

        $this->assertNotNull($result);
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
        $this->pageRepository->shouldReceive('duplicateProductsToSite')
            ->with($sourcePageId, $targetPageId, $targetSiteId)->once();
    }

    public function testClonePageToSiteClonesProducts()
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

        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1, 2);
        $this->assertNotNull($result);
    }

    public function testClonePageToSiteWithSelectiveRelations()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

        // Only these should be called
        $this->pageRepository->shouldReceive('duplicateBlocks')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCategoriesToSite')->with(1, 2, 2)->once();

        // These should NOT be called
        $this->pageRepository->shouldReceive('duplicateProductsToSite')->never();
        $this->pageRepository->shouldReceive('duplicateTagsToSite')->never();
        $this->pageRepository->shouldReceive('duplicateTerritoriesToSite')->never();

        // Still needed
        $this->pageRepository->shouldReceive('duplicateSeo')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateCustomFieldsToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicatePageAuthorsToSite')->with(1, 2, 2)->once();
        $this->pageRepository->shouldReceive('duplicateRegionSetsToSite')->with(1, 2, 2)->once();

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, 2, null, [
            'relations' => [
                'blocks' => true,
                'metadata' => true,
                'categories' => true,
                'products' => false,
                'tags' => false,
                'territories' => false,
            ]
        ]);

        $this->assertArrayHasKey('results', $result);
        $this->assertContains('blocks', $result['results']['success']);
        $this->assertContains('categories', $result['results']['success']);
        $this->assertContains('products', $result['results']['skipped']);
        $this->assertContains('tags', $result['results']['skipped']);
        $this->assertContains('territories', $result['results']['skipped']);
    }

    public function testClonePageToSiteReturnsDetailedResults()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, 2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_page_id', $result);
        $this->assertArrayHasKey('original_site_id', $result);
        $this->assertArrayHasKey('target_site_id', $result);
        $this->assertEquals(1, $result['original_page_id']);
        $this->assertEquals(1, $result['original_site_id']);
        $this->assertEquals(2, $result['target_site_id']);
    }

    public function testClonePageToSiteWithAllRelationsDisabled()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

        // No duplication methods should be called
        $this->pageRepository->shouldReceive('duplicateBlocks')->never();
        $this->pageRepository->shouldReceive('duplicateMetadata')->never();
        $this->pageRepository->shouldReceive('duplicateSeo')->never();
        $this->pageRepository->shouldReceive('duplicateSettings')->never();
        $this->pageRepository->shouldReceive('duplicateSocial')->never();
        $this->pageRepository->shouldReceive('duplicateCategoriesToSite')->never();
        $this->pageRepository->shouldReceive('duplicateTagsToSite')->never();
        $this->pageRepository->shouldReceive('duplicateCustomFieldsToSite')->never();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->never();
        $this->pageRepository->shouldReceive('duplicatePageAuthorsToSite')->never();
        $this->pageRepository->shouldReceive('duplicateRegionSetsToSite')->never();
        $this->pageRepository->shouldReceive('duplicateTerritoriesToSite')->never();
        $this->pageRepository->shouldReceive('duplicateProductsToSite')->never();

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, 2, null, [
            'relations' => [
                'blocks' => false,
                'metadata' => false,
                'seo' => false,
                'settings' => false,
                'social' => false,
                'categories' => false,
                'tags' => false,
                'customFields' => false,
                'accessRoles' => false,
                'pageAuthors' => false,
                'regionSets' => false,
                'territories' => false,
                'products' => false,
            ]
        ]);

        $this->assertCount(0, $result['results']['success']);
        $this->assertEquals(13, count($result['results']['skipped']));
    }

    public function testClonePageToSiteDefaultsToAllRelationsEnabled()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);
        $this->setupCloneToSiteExpectations(1, 2, 2);
        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, 2); // No options

        $this->assertGreaterThanOrEqual(13, count($result['results']['success']));
        $this->assertCount(0, $result['results']['skipped']);
    }

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

    public function testClonePageToSiteWithMixedRelationResults()
    {
        $sourcePage = $this->createMockPage(1);
        $sourcePage->site_id = 1;
        $newPage = $this->createMockPage(2);
        $newPage->site_id = 2;

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('slugExistsInSite')->andReturn(false);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($sourcePage, $newPage, 1, 2, 'cloned', 1, 2);

        // Mix of success, failure, and skipped
        $this->pageRepository->shouldReceive('duplicateBlocks')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateMetadata')->once()->andThrow(new \Exception('Metadata failed'));
        $this->pageRepository->shouldReceive('duplicateSeo')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSettings')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSocial')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCategoriesToSite')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicatePageAuthorsToSite')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateRegionSetsToSite')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCustomFieldsToSite')->once()->andReturn(true);
        // tags, products, territories are disabled

        $this->pageHistory->shouldReceive('logPageClonedToSite')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, 2, null, [
            'relations' => [
                'tags' => false,
                'products' => false,
                'territories' => false,
            ]
        ]);

        $this->assertGreaterThan(0, count($result['results']['success']));
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('metadata', $result['results']['failed'][0]['relation']);
        $this->assertCount(3, $result['results']['skipped']);
        $this->assertContains('tags', $result['results']['skipped']);
        $this->assertContains('products', $result['results']['skipped']);
        $this->assertContains('territories', $result['results']['skipped']);
    }

    private function setupTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
    }
}