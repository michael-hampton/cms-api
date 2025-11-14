<?php

namespace App\Tests\Unit\Actions;

use App\Actions\ClonePageToSite;
use App\Framework\Database\Database;
use App\Models\Page;
use App\Models\PageSettings;
use App\Repositories\AccessRoleRepository;
use App\Repositories\BlockRepository;
use App\Repositories\PageAuthorRepository;
use App\Repositories\PageCategoryRepository;
use App\Repositories\PageCustomFieldRepository;
use App\Repositories\PageMetadataRepository;
use App\Repositories\PageProductRepository;
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

        $this->service = new ClonePageToSite(
            $this->pageRepository,
           $this->databaseMock,
            $this->pageHistory,
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
}