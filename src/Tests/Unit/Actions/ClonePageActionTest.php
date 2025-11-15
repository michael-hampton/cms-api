<?php

namespace App\Tests\Unit\Actions;

use App\Actions\ClonePage;
use App\Framework\Database\Database;
use App\Models\Page;
use App\Models\PageHistory;
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
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

class ClonePageActionTest extends FunctionalTestCase
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

        $this->service = new ClonePage(
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
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);;
    }

    public function testDuplicatePageReturnsNullForNonexistent()
    {
        $this->pageRepository->shouldReceive('getCompletePageData')->with(999)->andReturn(null);
        // Verify products are duplicated during merge

        $result = $this->service->handle(999);

        $this->assertNull($result);
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
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($newPage);

        $result = $this->service->handle(1);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);;
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
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1);

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
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1);

        $this->assertNotNull($result);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithMetadata()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->pageRepository->shouldReceive('duplicateMetadata')->with(1, 2)->once();

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSeo()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSettings()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithSocial()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithCategories()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithTags()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithCustomFields()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithAccessRoles()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);

        $this->service->handle(1);
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

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1);

        $this->assertNotNull($result);
    }

    public function testDuplicatePageThrowsWhenAllRelationsFail()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);

        $this->pageRepository->shouldReceive('duplicateBlocks')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateMetadata')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSeo')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSettings')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateSocial')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateCategories')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateTags')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateCustomFields')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateRegionSets')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateTerritories')->andThrow(new \Exception('Failed'));
        $this->pageRepository->shouldReceive('duplicateProducts')->andThrow(new \Exception('Failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to duplicate any page relations');

        $this->service->handle(1);
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
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setDuplicationExpectations();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

//        set_error_handler(function ($errno, $errstr) {
//            return true;
//        });

        $result = $this->service->handle(1);

        //restore_error_handler();

        $this->assertNotNull($result);
    }

    public function testDuplicatePageClonesPageAuthors()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $result = $this->service->handle(1);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);;
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithRegionSets()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->handle(1);
    }

    #[DoesNotPerformAssertions]
    public function testDuplicatePageWithTerritories()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $this->service->handle(1);
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
        $this->pageRepository->shouldReceive('duplicateProducts')
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

    public function testDuplicatePageClonesProducts()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once()->with(1, 2)->andReturn(new PageHistory(['id' => 1]));

        $result = $this->service->handle(1);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);
    }

    public function testClonePageWithSelectiveRelations()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);

        // Only these should be called
        $this->pageRepository->shouldReceive('duplicateBlocks')->once();
        $this->pageRepository->shouldReceive('duplicateMetadata')->once();
        $this->pageRepository->shouldReceive('duplicateCategories')->once();

        // These should NOT be called
        $this->pageRepository->shouldReceive('duplicateProducts')->never();
        $this->pageRepository->shouldReceive('duplicateTags')->never();
        $this->pageRepository->shouldReceive('duplicateTerritories')->never();

        // Still needed
        $this->pageRepository->shouldReceive('duplicateSeo')->once();
        $this->pageRepository->shouldReceive('duplicateSettings')->once();
        $this->pageRepository->shouldReceive('duplicateSocial')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->once();
        $this->pageRepository->shouldReceive('duplicateRegionSets')->once();

        $this->pageHistory->shouldReceive('logPageDuplicated')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, [
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
    }

    public function testClonePageReturnsDetailedResults()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once();

        $result = $this->service->handle(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_page_id', $result);
        $this->assertArrayHasKey('success', $result['results']);
        $this->assertArrayHasKey('failed', $result['results']);
        $this->assertArrayHasKey('skipped', $result['results']);
        $this->assertEquals(1, $result['original_page_id']);
    }

    public function testClonePageWithAllRelationsDisabled()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);

        // No duplication methods should be called
        $this->pageRepository->shouldReceive('duplicateBlocks')->never();
        $this->pageRepository->shouldReceive('duplicateMetadata')->never();
        $this->pageRepository->shouldReceive('duplicateSeo')->never();
        $this->pageRepository->shouldReceive('duplicateSettings')->never();
        $this->pageRepository->shouldReceive('duplicateSocial')->never();
        $this->pageRepository->shouldReceive('duplicateCategories')->never();
        $this->pageRepository->shouldReceive('duplicateTags')->never();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->never();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->never();
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->never();
        $this->pageRepository->shouldReceive('duplicateRegionSets')->never();
        $this->pageRepository->shouldReceive('duplicateTerritories')->never();
        $this->pageRepository->shouldReceive('duplicateProducts')->never();

        $this->pageHistory->shouldReceive('logPageDuplicated')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1, [
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

    public function testClonePageDefaultsToAllRelationsEnabled()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->setupDuplicatePageExpectations($originalPage, $newPage);
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);
        $this->pageHistory->shouldReceive('logPageDuplicated')->once();

        $result = $this->service->handle(1); // No options passed

        // All relations should be in success (13 total)
        $this->assertGreaterThanOrEqual(13, count($result['results']['success']));
        $this->assertCount(0, $result['results']['skipped']);
    }

    public function testClonePageTracksMultipleFailures()
    {
        $originalPage = $this->createMockPage(1);
        $newPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->andReturn($originalPage);
        $this->setupTransaction();
        $this->pageRepository->shouldReceive('create')->andReturn($newPage);
        $this->setCloneHistoryExpectations($originalPage, $newPage, 1, 2);

        // Multiple failures
        $this->pageRepository->shouldReceive('duplicateBlocks')->once()->andThrow(new \Exception('Blocks failed'));
        $this->pageRepository->shouldReceive('duplicateMetadata')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSeo')->once()->andThrow(new \Exception('SEO failed'));
        $this->pageRepository->shouldReceive('duplicateSettings')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateSocial')->once()->andThrow(new \Exception('Social failed'));
        $this->pageRepository->shouldReceive('duplicateCategories')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTags')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateRegionSets')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTerritories')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateProducts')->once()->andReturn(true);

        $this->pageHistory->shouldReceive('logPageDuplicated')->once();
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->andReturn($newPage);

        $result = $this->service->handle(1);

        $this->assertCount(3, $result['results']['failed']);
        $this->assertEquals('blocks', $result['results']['failed'][0]['relation']);
        $this->assertEquals('seo', $result['results']['failed'][1]['relation']);
        $this->assertEquals('social', $result['results']['failed'][2]['relation']);
        $this->assertGreaterThan(0, count($result['results']['success']));
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