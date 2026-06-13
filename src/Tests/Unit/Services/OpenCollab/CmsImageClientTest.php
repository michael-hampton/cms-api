<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Framework\Http\UploadedFile;
use App\Repositories\Cms\ImageRepository;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Cms\ImageService;
use App\Services\OpenCollab\CmsImageClient;
use Mockery;

class CmsImageClientTest extends OpenCollabTestCase
{
    private ImageService    $imageService;
    private ImageRepository $imageRepository;
    private CmsImageClient  $client;

    private const SITE_ID = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageService    = Mockery::mock(ImageService::class);
        $this->imageRepository = Mockery::mock(ImageRepository::class);

        $this->client = new CmsImageClient($this->imageService, $this->imageRepository);
    }

    // ── search() ─────────────────────────────────────────────────────────────

    public function test_search_builds_criteria_from_query_and_delegates_to_repository(): void
    {
        $query  = new ImageSearchQuery(page: 2, perPage: 10, search: 'garden', uploadedBy: 42);
        $result = Mockery::mock(PaginatedResult::class);

        $this->imageRepository
            ->shouldReceive('searchForSite')
            ->once()
            ->withArgs(function (SearchCriteria $criteria) {
                return $criteria->getPage() === 2
                    && $criteria->getPerPage() === 10
                    && $criteria->filters['site_id'] === self::SITE_ID
                    && $criteria->filters['uploaded_by'] === 42
                    && $criteria->getSearchQuery() === 'garden';
            })
            ->andReturn($result);

        $returned = $this->client->search(self::SITE_ID, $query);

        $this->assertSame($result, $returned);
    }

    public function test_search_caps_per_page_at_100(): void
    {
        $query = new ImageSearchQuery(perPage: 999);

        $this->imageRepository
            ->shouldReceive('searchForSite')
            ->once()
            ->withArgs(fn(SearchCriteria $c) => $c->getPerPage() === 100)
            ->andReturn(Mockery::mock(PaginatedResult::class));

        $this->client->search(self::SITE_ID, $query);
    }

    public function test_search_omits_null_filters_from_criteria(): void
    {
        $query = new ImageSearchQuery(); // all nulls

        $this->imageRepository
            ->shouldReceive('searchForSite')
            ->once()
            ->withArgs(function (SearchCriteria $criteria) {
                return !array_key_exists('uploaded_by', $criteria->filters)
                    && !array_key_exists('image_rights', $criteria->filters)
                    && !array_key_exists('uploaded_from', $criteria->filters);
            })
            ->andReturn(Mockery::mock(PaginatedResult::class));

        $this->client->search(self::SITE_ID, $query);
    }

    public function test_search_includes_image_rights_filter_when_set(): void
    {
        $query = new ImageSearchQuery(imageRights: OpenCollabImageRights::Agency);

        $this->imageRepository
            ->shouldReceive('searchForSite')
            ->once()
            ->withArgs(fn(SearchCriteria $c) => ($c->filters['image_rights'] ?? null) === 'agency')
            ->andReturn(Mockery::mock(PaginatedResult::class));

        $this->client->search(self::SITE_ID, $query);
    }

    // ── find() ────────────────────────────────────────────────────────────────

    public function test_find_returns_image_when_it_exists_on_the_correct_site(): void
    {
        $image = $this->makeImage(['id' => 5, 'site_id' => self::SITE_ID, 'is_active' => true]);

        $this->imageService->shouldReceive('getImage')->with(5)->andReturn($image);

        $result = $this->client->find(self::SITE_ID, 5);

        $this->assertSame($image, $result);
    }

    public function test_find_returns_null_when_image_not_found(): void
    {
        $this->imageService->shouldReceive('getImage')->with(999)->andReturn(null);

        $result = $this->client->find(self::SITE_ID, 999);

        $this->assertNull($result);
    }

    public function test_find_returns_null_when_image_belongs_to_different_site(): void
    {
        $image = $this->makeImage(['site_id' => 99]); // wrong site
        $this->imageService->shouldReceive('getImage')->andReturn($image);

        $result = $this->client->find(self::SITE_ID, 5);

        $this->assertNull($result);
    }

    public function test_find_returns_null_when_image_is_inactive(): void
    {
        $image = $this->makeImage(['site_id' => self::SITE_ID, 'is_active' => false]);
        $this->imageService->shouldReceive('getImage')->andReturn($image);

        $result = $this->client->find(self::SITE_ID, 5);

        $this->assertNull($result);
    }

    // ── findMany() ────────────────────────────────────────────────────────────

    public function test_find_many_returns_empty_array_for_empty_id_list(): void
    {
        $this->imageRepository->shouldNotReceive('findManyForSite');

        $result = $this->client->findMany(self::SITE_ID, []);

        $this->assertSame([], $result);
    }

    public function test_find_many_returns_active_images_keyed_by_id(): void
    {
        $imageA = $this->makeImage(['id' => 1, 'is_active' => true]);
        $imageB = $this->makeImage(['id' => 2, 'is_active' => true]);

        $this->imageRepository
            ->shouldReceive('findManyForSite')
            ->with(self::SITE_ID, [1, 2])
            ->andReturn(collect([$imageA, $imageB]));

        $result = $this->client->findMany(self::SITE_ID, [1, 2]);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame($imageA, $result[1]);
        $this->assertSame($imageB, $result[2]);
    }

    public function test_find_many_excludes_inactive_images(): void
    {
        $active   = $this->makeImage(['id' => 1, 'is_active' => true]);
        $inactive = $this->makeImage(['id' => 2, 'is_active' => false]);

        $this->imageRepository
            ->shouldReceive('findManyForSite')
            ->andReturn(collect([$active, $inactive]));

        $result = $this->client->findMany(self::SITE_ID, [1, 2]);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
    }

    public function test_find_many_deduplicates_ids_before_repository_call(): void
    {
        $this->imageRepository
            ->shouldReceive('findManyForSite')
            ->once()
            ->with(4, [
                0 => 1,
                2 => 2,
            ])
            ->andReturn(collect([]));

        $this->client->findMany(4, [1, 1, 2]);
    }

    // ── upload() ─────────────────────────────────────────────────────────────

    public function test_upload_delegates_to_image_service_with_all_fields(): void
    {
        $file  = Mockery::mock(UploadedFile::class);
        $image = $this->makeImage();

        $uploadData = new ImageUploadData(
            file:          $file,
            name:          'Summer garden',
            imageRights:   OpenCollabImageRights::ContributorOwned,
            altText:       'A garden in bloom',
            credit:        'Jane Smith',
            sourceContext: 'open_collab_article_editor',
        );

        $this->imageService
            ->shouldReceive('uploadImage')
            ->once()
            ->withArgs(function (UploadedFile $f, array $meta) use ($file) {
                return $f === $file
                    && $meta['name'] === 'Summer garden'
                    && $meta['image_rights'] === 'contributor_owned'
                    && $meta['alt_text'] === 'A garden in bloom'
                    && $meta['credit'] === 'Jane Smith'
                    && $meta['source_context'] === 'open_collab_article_editor'
                    && $meta['site_id'] === self::SITE_ID;
            })
            ->andReturn($image);

        $result = $this->client->upload(self::SITE_ID, $uploadData);

        $this->assertSame($image, $result);
    }

    public function test_upload_includes_external_reference_when_provided(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $uploadData = new ImageUploadData(
            file:              $file,
            name:              'Photo',
            imageRights:       OpenCollabImageRights::StaffOwned,
            altText:           'Alt',
            credit:            '',
            sourceContext:     'open_collab_article_editor',
            externalReference: 'ext-001',
        );

        $this->imageService
            ->shouldReceive('uploadImage')
            ->once()
            ->withArgs(fn($f, array $meta) => ($meta['external_reference'] ?? null) === 'ext-001')
            ->andReturn($this->makeImage());

        $this->client->upload(self::SITE_ID, $uploadData);
    }
}