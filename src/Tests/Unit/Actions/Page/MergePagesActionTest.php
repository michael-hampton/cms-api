<?php

namespace App\Tests\Unit\Actions\Page;

use App\Actions\Pages\MergePages;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Block;
use App\Models\Page;
use App\Models\PageCustomField;
use App\Models\PageMetadata;
use App\Models\PageSeo;
use App\Models\PageSettings;
use App\Models\PageSocial;
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
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery;

class MergePagesActionTest extends FunctionalTestCase
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

        $this->service = new MergePages(
            $this->pageRepository,
            $this->blockRepository,
            $this->customFieldRepository,
            $this->databaseMock,
            $this->siteId
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
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

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(10, $result['results']['success']);;
    }

    public function testMergePagesWithReplaceStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($targetPage);
        $this->setupTransaction();

        $this->blockRepository->shouldReceive('deletePageBlocks')->with(2)->once();
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
        $this->setDuplicationExpectations();

        $customFields = collect([]);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(1)->once()->andReturn($customFields);
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->with(2)->once()->andReturn($customFields);

        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->once()->andReturn($targetPage);

        $result = $this->service->mergePages(1, 2, ['strategy' => 'replace']);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(13, $result['results']['success']);;
    }

    public function testMergePagesWithKeepTargetStrategy()
    {
        $sourcePage = $this->createMockPage(1, 'Source Page');
        $targetPage = $this->createMockPage(2, 'Target Page');

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
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

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(9, $result['results']['success']);;
    }

    public function testMergePagesDeletesSourceAfterSuccess()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->pageRepository->shouldReceive('getCompletePageData')->with(1)->once()->andReturn($sourcePage);
        $this->pageRepository->shouldReceive('getCompletePageData')->with(2)->twice()->andReturn($targetPage);
        $this->setupTransaction();
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->once()->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(10, $result['results']['success']);;
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
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
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
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
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
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
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
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
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

        $this->assertNotEmpty($result['results']['success']);
        $this->assertEmpty($result['results']['failed']);
        $this->assertCount(10, $result['results']['success']);;
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

    public function testMergePagesWithProducts()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setDuplicationExpectations();

        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');

        $this->blockRepository->shouldReceive('getMaxOrder')->with(2)->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->with(1)->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2);
        $this->assertNotNull($result);
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

    public function testMergePagesWithSelectiveRelations()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');

        // Only these relations should be called
        $this->pageRepository->shouldReceive('duplicateCategories')->once();
        $this->pageRepository->shouldReceive('duplicateTags')->once();

        // These should NOT be called
        $this->pageRepository->shouldReceive('duplicateProducts')->never();
        $this->pageRepository->shouldReceive('duplicateTerritories')->never();
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->never();

        // Still needed for other operations
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once();
        $this->pageRepository->shouldReceive('duplicateRegionSets')->once();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2, [
            'strategy' => 'append',
            'relations' => [
                'categories' => true,
                'tags' => true,
                'products' => false,
                'territories' => false,
                'pageAuthors' => false,
            ]
        ]);

        $this->assertArrayHasKey('results', $result);
        $this->assertContains('categories', $result['results']['success']);
        $this->assertContains('tags', $result['results']['success']);
        $this->assertContains('products', $result['results']['skipped']);
        $this->assertContains('territories', $result['results']['skipped']);
        $this->assertContains('pageAuthors', $result['results']['skipped']);
    }

    public function testMergePagesReturnsDetailedResults()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');
        $this->setDuplicationExpectations();

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('source_page_id', $result);
        $this->assertArrayHasKey('target_page_id', $result);
        $this->assertEquals(1, $result['source_page_id']);
        $this->assertEquals(2, $result['target_page_id']);
    }

    public function testMergePagesWithAllRelationsDisabled()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');

        // No relation methods should be called
        $this->pageRepository->shouldReceive('duplicateCategories')->never();
        $this->pageRepository->shouldReceive('duplicateTags')->never();
        $this->pageRepository->shouldReceive('duplicateProducts')->never();
        $this->pageRepository->shouldReceive('duplicateTerritories')->never();
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->never();
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->never();
        $this->pageRepository->shouldReceive('duplicateRegionSets')->never();
        $this->pageRepository->shouldReceive('duplicateCustomFields')->never();

        $customFieldCollection = $this->createCustomFieldCollection();

        $this->blockRepository->shouldReceive('getMaxOrder')->never();
        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $customFieldCollection = $this->createCustomFieldCollection();

        // Add this line:
//        $customFieldCollection->shouldReceive('pluck')
//            ->with('custom_field_definition_id')
//            ->andReturn(collect([1]));
//
//        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->once()
//            ->andReturn($customFieldCollection);
//
//        $this->setDuplicationExpectations();


        $result = $this->service->mergePages(1, 2, [
            'strategy' => 'append',
            'relations' => [
                'categories' => false,
                'tags' => false,
                'products' => false,
                'territories' => false,
                'pageAuthors' => false,
                'accessRoles' => false,
                'regionSets' => false,
                'customFields' => false,
                'blocks' => false,
                'settings' => false,
            ]
        ]);

        $this->assertCount(0, $result['results']['success']);
        $this->assertGreaterThan(0, count($result['results']['skipped']));
    }

    public function testMergePagesWithPartialRelationFailure()
    {
        $sourcePage = $this->createMockPage(1);
        $targetPage = $this->createMockPage(2);

        $this->setupMergePageExpectations($sourcePage, $targetPage);
        $this->setCloneHistoryExpectations($sourcePage, $targetPage, 1, 2, 'merged');

        $this->pageRepository->shouldReceive('duplicateCategories')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTags')->once()->andThrow(new \Exception('Tag merge failed'));
        $this->pageRepository->shouldReceive('duplicateProducts')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateAccessRoles')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateRegionSets')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateTerritories')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicatePageAuthors')->once()->andReturn(true);
        $this->pageRepository->shouldReceive('duplicateCustomFields')->once()->andReturn(true);

        $this->blockRepository->shouldReceive('getMaxOrder')->andReturn(0);
        $this->blockRepository->shouldReceive('getBlocksForPage')->andReturn(collect([]));
        $this->customFieldRepository->shouldReceive('getCustomFieldsForPage')->andReturn(collect([]));
        $this->pageRepository->shouldReceive('delete')->with(1)->andReturn(true);

        $result = $this->service->mergePages(1, 2);

        $this->assertContains('categories', $result['results']['success']);
        $this->assertContains('products', $result['results']['success']);
        $this->assertCount(1, $result['results']['failed']);
        $this->assertEquals('tags', $result['results']['failed'][0]['relation']);
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

    private function invokePrivateMethod(string $methodName, ...$args)
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($this->service, ...$args);
    }
}