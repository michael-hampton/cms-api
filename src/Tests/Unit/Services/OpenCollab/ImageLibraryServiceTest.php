<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\OpenCollab\ImageEvidenceData;
use App\DTO\OpenCollab\ImageSearchQuery;
use App\DTO\OpenCollab\ImageUploadData;
use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Exceptions\OpenCollab\ImageLibraryAccessDeniedException;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\Logger;
use App\Search\PaginatedResult;
use App\Services\OpenCollab\CmsImageClientInterface;
use App\Services\OpenCollab\ImageLibraryService;
use App\Services\OpenCollab\ImageSubmissionEvidenceServiceInterface;
use App\Services\OpenCollab\Policies\ContributorImagePolicyInterface;
use App\Services\OpenCollab\Risk\CreatorDeclarationRiskService;
use App\Services\OpenCollab\Risk\ImageMetadataRiskService;
use Mockery;

class ImageLibraryServiceTest extends OpenCollabTestCase
{
    private CmsImageClientInterface $cmsClient;

    private ContributorImagePolicyInterface $imagePolicy;

    private ImageSubmissionEvidenceServiceInterface $evidenceService;

    private CreatorDeclarationRiskService $creatorDeclarationRiskService;

    private ImageMetadataRiskService $imageMetadataRiskService;

    private Logger $logger;

    private ImageLibraryService $service;

    private const USER_ID = 42;

    private const SITE_ID = 4;

    private const IMAGE_ID = 99;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cmsClient = Mockery::mock(
            CmsImageClientInterface::class,
        );

        $this->imagePolicy = Mockery::mock(
            ContributorImagePolicyInterface::class,
        );

        $this->evidenceService = Mockery::mock(
            ImageSubmissionEvidenceServiceInterface::class,
        );

        $this->creatorDeclarationRiskService = Mockery::mock(
            CreatorDeclarationRiskService::class,
        );

        $this->imageMetadataRiskService = Mockery::mock(
            ImageMetadataRiskService::class,
        );

        $this->logger = Mockery::mock(Logger::class);
        $this->logger->shouldIgnoreMissing();

        $this->service = new ImageLibraryService(
            $this->cmsClient,
            $this->imagePolicy,
            $this->evidenceService,
            $this->creatorDeclarationRiskService,
            $this->imageMetadataRiskService,
            $this->logger,
        );
    }

    // ── search() ─────────────────────────────────────────────────────────────

    public function test_search_throws_access_denied_when_contributor_cannot_browse(): void
    {
        $site = $this->makeSite();

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(false);

        $this->cmsClient->shouldNotReceive('search');

        $this->expectException(
            ImageLibraryAccessDeniedException::class,
        );

        $this->service->search(
            self::USER_ID,
            $site,
            new ImageSearchQuery(),
        );
    }

    public function test_search_delegates_to_cms_client_with_scoped_query(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $query = new ImageSearchQuery(
            page: 2,
            perPage: 15,
            search: 'garden',
        );

        $result = Mockery::mock(PaginatedResult::class);

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('search')
            ->once()
            ->withArgs(
                static function (
                    int $siteId,
                    ImageSearchQuery $query,
                ): bool {
                    return $siteId === self::SITE_ID
                        && $query->uploadedBy === self::USER_ID
                        && $query->page === 2
                        && $query->perPage === 15
                        && $query->search === 'garden';
                },
            )
            ->andReturn($result);

        $returned = $this->service->search(
            self::USER_ID,
            $site,
            $query,
        );

        $this->assertSame($result, $returned);
    }

    public function test_search_preserves_explicit_uploaded_by_from_query(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $query = new ImageSearchQuery(
            uploadedBy: 99,
        );

        $result = Mockery::mock(PaginatedResult::class);

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('search')
            ->once()
            ->withArgs(
                static fn(
                    int $siteId,
                    ImageSearchQuery $query,
                ): bool =>
                    $siteId === self::SITE_ID
                    && $query->uploadedBy === 99,
            )
            ->andReturn($result);

        $returned = $this->service->search(
            self::USER_ID,
            $site,
            $query,
        );

        $this->assertSame($result, $returned);
    }

    // ── findForContributor() ─────────────────────────────────────────────────

    public function test_find_for_contributor_throws_when_contributor_cannot_browse(): void
    {
        $site = $this->makeSite();

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(false);

        $this->cmsClient->shouldNotReceive('find');

        $this->expectException(
            ImageLibraryAccessDeniedException::class,
        );

        $this->service->findForContributor(
            self::USER_ID,
            $site,
            self::IMAGE_ID,
        );
    }

    public function test_find_for_contributor_returns_null_when_image_not_found(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('find')
            ->once()
            ->with(self::SITE_ID, self::IMAGE_ID)
            ->andReturn(null);

        $this->imagePolicy->shouldNotReceive('canUse');

        $result = $this->service->findForContributor(
            self::USER_ID,
            $site,
            self::IMAGE_ID,
        );

        $this->assertNull($result);
    }

    public function test_find_for_contributor_returns_null_when_contributor_cannot_use_image(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => self::IMAGE_ID,
        ]);

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('find')
            ->once()
            ->with(self::SITE_ID, self::IMAGE_ID)
            ->andReturn($image);

        $this->imagePolicy
            ->shouldReceive('canUse')
            ->once()
            ->with(self::USER_ID, $site, $image)
            ->andReturn(false);

        $result = $this->service->findForContributor(
            self::USER_ID,
            $site,
            self::IMAGE_ID,
        );

        $this->assertNull($result);
    }

    public function test_find_for_contributor_returns_image_when_contributor_may_use_it(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => self::IMAGE_ID,
        ]);

        $this->imagePolicy
            ->shouldReceive('canBrowse')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('find')
            ->once()
            ->with(self::SITE_ID, self::IMAGE_ID)
            ->andReturn($image);

        $this->imagePolicy
            ->shouldReceive('canUse')
            ->once()
            ->with(self::USER_ID, $site, $image)
            ->andReturn(true);

        $result = $this->service->findForContributor(
            self::USER_ID,
            $site,
            self::IMAGE_ID,
        );

        $this->assertSame($image, $result);
    }

    // ── upload() ─────────────────────────────────────────────────────────────

    public function test_upload_throws_access_denied_when_contributor_cannot_upload(): void
    {
        $site = $this->makeSite();

        $this->imagePolicy
            ->shouldReceive('canUpload')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(false);

        $this->cmsClient->shouldNotReceive('upload');
        $this->evidenceService->shouldNotReceive('record');

        $this->creatorDeclarationRiskService
            ->shouldNotReceive('recordForImageUpload');

        $this->imageMetadataRiskService
            ->shouldNotReceive('inspectUploadedImage');

        $this->expectException(
            ImageLibraryAccessDeniedException::class,
        );

        $this->service->upload(
            self::USER_ID,
            $site,
            $this->makeUploadData(),
            $this->makeEvidenceData(),
        );
    }

    public function test_upload_calls_cms_client_records_risks_and_records_evidence(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => self::IMAGE_ID,
        ]);

        $uploadData = $this->makeUploadData();
        $evidenceData = $this->makeEvidenceData();

        $this->imagePolicy
            ->shouldReceive('canUpload')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('upload')
            ->once()
            ->with(self::SITE_ID, $uploadData)
            ->andReturn($image);

        $this->creatorDeclarationRiskService
            ->shouldReceive('recordForImageUpload')
            ->once()
            ->withArgs(
                static function (
                    int $siteId,
                    int $cmsImageId,
                    int $contributorUserId,
                    bool $aiGenerated,
                    bool $containsMusic,
                    bool $sponsoredContent,
                    bool $affiliateContent,
                    bool $unclearRights,
                    OpenCollabImageRights $imageRights,
                ): bool {
                    return $siteId === self::SITE_ID
                        && $cmsImageId === self::IMAGE_ID
                        && $contributorUserId === self::USER_ID
                        && $aiGenerated === false
                        && $containsMusic === false
                        && $sponsoredContent === false
                        && $affiliateContent === false
                        && $unclearRights === false
                        && $imageRights === OpenCollabImageRights::ContributorOwned;
                },
            );

        $this->imageMetadataRiskService
            ->shouldReceive('inspectUploadedImage')
            ->once()
            ->withArgs(
                static function (
                    int $siteId,
                    int $cmsImageId,
                    int $actorUserId,
                    OpenCollabImageRights $imageRights,
                    string $altText,
                    string $credit,
                    bool $aiGenerated,
                ): bool {
                    return $siteId === self::SITE_ID
                        && $cmsImageId === self::IMAGE_ID
                        && $actorUserId === self::USER_ID
                        && $imageRights === OpenCollabImageRights::ContributorOwned
                        && $altText === 'Test alt'
                        && $credit === 'Jane'
                        && $aiGenerated === false;
                },
            );

        $this->evidenceService
            ->shouldReceive('record')
            ->once()
            ->withArgs(
                static function (
                    ImageEvidenceData $recorded,
                ): bool {
                    return $recorded->siteId === self::SITE_ID
                        && $recorded->cmsImageId === self::IMAGE_ID
                        && $recorded->contributorUserId === self::USER_ID
                        && $recorded->imageRights === OpenCollabImageRights::ContributorOwned
                        && $recorded->nameSubmitted === 'Test image'
                        && $recorded->altTextSubmitted === 'Test alt'
                        && $recorded->creditSubmitted === 'Jane'
                        && $recorded->rightsConfirmation === true;
                },
            );

        $result = $this->service->upload(
            self::USER_ID,
            $site,
            $uploadData,
            $evidenceData,
        );

        $this->assertSame($image, $result);
    }

    public function test_upload_uses_actual_uploaded_image_id_when_recording_evidence(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $actualImageId = 123;

        $image = $this->makeImage([
            'id' => $actualImageId,
        ]);

        $evidenceData = new ImageEvidenceData(
            siteId: self::SITE_ID,
            cmsImageId: 999,
            contributorUserId: self::USER_ID,
            imageRights: OpenCollabImageRights::ContributorOwned,
            nameSubmitted: 'Test image',
            altTextSubmitted: 'Test alt',
            creditSubmitted: 'Jane',
            rightsConfirmation: true,
        );

        $this->imagePolicy
            ->shouldReceive('canUpload')
            ->once()
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('upload')
            ->once()
            ->andReturn($image);

        $this->expectRiskServicesForImage($actualImageId);

        $this->evidenceService
            ->shouldReceive('record')
            ->once()
            ->withArgs(
                static fn(
                    ImageEvidenceData $recorded,
                ): bool =>
                    $recorded->cmsImageId === $actualImageId,
            );

        $result = $this->service->upload(
            self::USER_ID,
            $site,
            $this->makeUploadData(),
            $evidenceData,
        );

        $this->assertSame($image, $result);
    }

    public function test_upload_returns_image_when_evidence_recording_fails(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => self::IMAGE_ID,
        ]);

        $this->imagePolicy
            ->shouldReceive('canUpload')
            ->once()
            ->with(self::USER_ID, $site)
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('upload')
            ->once()
            ->andReturn($image);

        $this->expectRiskServicesForImage(self::IMAGE_ID);

        $this->evidenceService
            ->shouldReceive('record')
            ->once()
            ->andThrow(
                new \RuntimeException('DB connection lost'),
            );

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('Image evidence recording failed.', Mockery::on(
                fn(array $context): bool => $context['image_id'] === self::IMAGE_ID
                    && $context['error'] === 'DB connection lost'
            ));

        $result = $this->service->upload(
            self::USER_ID,
            $site,
            $this->makeUploadData(),
            $this->makeEvidenceData(),
        );

        $this->assertSame($image, $result);
    }

    public function test_upload_does_not_record_evidence_when_creator_risk_recording_fails(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => self::IMAGE_ID,
        ]);

        $this->imagePolicy
            ->shouldReceive('canUpload')
            ->once()
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('upload')
            ->once()
            ->andReturn($image);

        $this->creatorDeclarationRiskService
            ->shouldReceive('recordForImageUpload')
            ->once()
            ->andThrow(
                new \RuntimeException('Risk recording failed'),
            );

        $this->imageMetadataRiskService
            ->shouldNotReceive('inspectUploadedImage');

        $this->evidenceService->shouldNotReceive('record');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Risk recording failed');

        $this->service->upload(
            self::USER_ID,
            $site,
            $this->makeUploadData(),
            $this->makeEvidenceData(),
        );
    }

    public function test_upload_does_not_record_evidence_when_metadata_inspection_fails(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => self::IMAGE_ID,
        ]);

        $this->imagePolicy
            ->shouldReceive('canUpload')
            ->once()
            ->andReturn(true);

        $this->cmsClient
            ->shouldReceive('upload')
            ->once()
            ->andReturn($image);

        $this->creatorDeclarationRiskService
            ->shouldReceive('recordForImageUpload')
            ->once();

        $this->imageMetadataRiskService
            ->shouldReceive('inspectUploadedImage')
            ->once()
            ->andThrow(
                new \RuntimeException('Metadata inspection failed'),
            );

        $this->evidenceService->shouldNotReceive('record');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Metadata inspection failed');

        $this->service->upload(
            self::USER_ID,
            $site,
            $this->makeUploadData(),
            $this->makeEvidenceData(),
        );
    }

    // ── resolveForEditor() ───────────────────────────────────────────────────

    public function test_resolve_for_editor_returns_empty_array_for_no_image_ids(): void
    {
        $site = $this->makeSite();

        $this->cmsClient->shouldNotReceive('findMany');
        $this->imagePolicy->shouldNotReceive('canUse');

        $result = $this->service->resolveForEditor(
            self::USER_ID,
            $site,
            [],
        );

        $this->assertSame([], $result);
    }

    public function test_resolve_for_editor_returns_images_the_contributor_can_use(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image1 = $this->makeImage([
            'id' => 1,
        ]);

        $image2 = $this->makeImage([
            'id' => 2,
        ]);

        $this->cmsClient
            ->shouldReceive('findMany')
            ->once()
            ->with(self::SITE_ID, [1, 2])
            ->andReturn([
                1 => $image1,
                2 => $image2,
            ]);

        $this->imagePolicy
            ->shouldReceive('canUse')
            ->once()
            ->with(self::USER_ID, $site, $image1)
            ->andReturn(true);

        $this->imagePolicy
            ->shouldReceive('canUse')
            ->once()
            ->with(self::USER_ID, $site, $image2)
            ->andReturn(true);

        $result = $this->service->resolveForEditor(
            self::USER_ID,
            $site,
            [1, 2],
        );

        $this->assertSame([
            1 => $image1,
            2 => $image2,
        ], $result);
    }

    public function test_resolve_for_editor_nullifies_images_contributor_cannot_use(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $image = $this->makeImage([
            'id' => 1,
        ]);

        $this->cmsClient
            ->shouldReceive('findMany')
            ->once()
            ->with(self::SITE_ID, [1])
            ->andReturn([
                1 => $image,
            ]);

        $this->imagePolicy
            ->shouldReceive('canUse')
            ->once()
            ->with(self::USER_ID, $site, $image)
            ->andReturn(false);

        $result = $this->service->resolveForEditor(
            self::USER_ID,
            $site,
            [1],
        );

        $this->assertSame([
            1 => null,
        ], $result);
    }

    public function test_resolve_for_editor_produces_null_entry_for_missing_images(): void
    {
        $site = $this->makeSite([
            'id' => self::SITE_ID,
        ]);

        $this->cmsClient
            ->shouldReceive('findMany')
            ->once()
            ->with(self::SITE_ID, [1, 2])
            ->andReturn([]);

        $this->imagePolicy->shouldNotReceive('canUse');

        $result = $this->service->resolveForEditor(
            self::USER_ID,
            $site,
            [1, 2],
        );

        $this->assertSame([
            1 => null,
            2 => null,
        ], $result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function expectRiskServicesForImage(int $imageId): void
    {
        $this->creatorDeclarationRiskService
            ->shouldReceive('recordForImageUpload')
            ->once()
            ->withArgs(
                static fn(
                    int $siteId,
                    int $cmsImageId,
                    int $contributorUserId,
                ): bool =>
                    $siteId === self::SITE_ID
                    && $cmsImageId === $imageId
                    && $contributorUserId === self::USER_ID,
            );

        $this->imageMetadataRiskService
            ->shouldReceive('inspectUploadedImage')
            ->once()
            ->withArgs(
                static fn(
                    int $siteId,
                    int $cmsImageId,
                    int $actorUserId,
                ): bool =>
                    $siteId === self::SITE_ID
                    && $cmsImageId === $imageId
                    && $actorUserId === self::USER_ID,
            );
    }

    private function makeUploadData(): ImageUploadData
    {
        return new ImageUploadData(
            file: Mockery::mock(UploadedFile::class),
            name: 'Test image',
            imageRights: OpenCollabImageRights::ContributorOwned,
            altText: 'Test alt',
            credit: 'Jane',
            sourceContext: 'open_collab_article_editor',
        );
    }

    private function makeEvidenceData(): ImageEvidenceData
    {
        return new ImageEvidenceData(
            siteId: self::SITE_ID,
            cmsImageId: self::IMAGE_ID,
            contributorUserId: self::USER_ID,
            imageRights: OpenCollabImageRights::ContributorOwned,
            nameSubmitted: 'Test image',
            altTextSubmitted: 'Test alt',
            creditSubmitted: 'Jane',
            rightsConfirmation: true,
        );
    }
}