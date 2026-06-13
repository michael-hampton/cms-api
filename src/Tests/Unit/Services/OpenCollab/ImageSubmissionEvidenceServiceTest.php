<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\OpenCollab\ImageEvidenceData;
use App\Enums\OpenCollab\OpenCollabImageRights;
use App\Repositories\OpenCollab\ImageSubmissionEvidenceRepositoryInterface;
use App\Services\OpenCollab\ImageSubmissionEvidenceService;
use Mockery;

class ImageSubmissionEvidenceServiceTest extends OpenCollabTestCase
{
    private ImageSubmissionEvidenceRepositoryInterface $repository;
    private ImageSubmissionEvidenceService             $service;

    private const SITE_ID   = 4;
    private const IMAGE_ID  = 99;
    private const USER_ID   = 42;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ImageSubmissionEvidenceRepositoryInterface::class);
        $this->service    = new ImageSubmissionEvidenceService($this->repository);
    }

    // ── record() ─────────────────────────────────────────────────────────────

    public function test_record_creates_evidence_with_all_submitted_values(): void
    {
        $data     = $this->makeData();
        $evidence = $this->makeEvidence(['cms_image_id' => self::IMAGE_ID]);

        $this->repository->shouldReceive('findByCorrelationId')->never();
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload) use ($data) {
                return $payload['site_id']                === self::SITE_ID
                    && $payload['cms_image_id']           === self::IMAGE_ID
                    && $payload['contributor_user_id']    === self::USER_ID
                    && $payload['cms_image_rights_value'] === OpenCollabImageRights::ContributorOwned->value
                    && $payload['name_submitted']         === 'Summer garden'
                    && $payload['alt_text_submitted']     === 'A garden in bloom'
                    && $payload['credit_submitted']       === 'Jane Smith'
                    && $payload['rights_confirmation']    === true
                    && $payload['ai_generated']           === false
                    && $payload['sponsored_content']      === false
                    && $payload['affiliate_content']      === false;
            })
            ->andReturn($evidence);

        $result = $this->service->record($data);

        $this->assertSame($evidence, $result);
    }

    public function test_record_returns_existing_record_when_correlation_id_already_exists(): void
    {
        $existing = $this->makeEvidence(['request_correlation_id' => 'req-abc']);
        $data     = $this->makeData(correlationId: 'req-abc');

        $this->repository
            ->shouldReceive('findByCorrelationId')
            ->with('req-abc')
            ->andReturn($existing);

        // create must NOT be called — idempotent
        $this->repository->shouldNotReceive('create');

        $result = $this->service->record($data);

        $this->assertSame($existing, $result);
    }

    public function test_record_creates_new_record_when_correlation_id_is_not_yet_stored(): void
    {
        $data     = $this->makeData(correlationId: 'req-new');
        $evidence = $this->makeEvidence();

        $this->repository
            ->shouldReceive('findByCorrelationId')
            ->with('req-new')
            ->andReturn(null);

        $this->repository->shouldReceive('create')->once()->andReturn($evidence);

        $result = $this->service->record($data);

        $this->assertSame($evidence, $result);
    }

    public function test_record_skips_correlation_id_lookup_when_not_provided(): void
    {
        $data     = $this->makeData(correlationId: null);
        $evidence = $this->makeEvidence();

        // No correlation ID — must not query for duplicates
        $this->repository->shouldNotReceive('findByCorrelationId');
        $this->repository->shouldReceive('create')->once()->andReturn($evidence);

        $this->service->record($data);
    }

    public function test_record_stores_ip_address_and_user_agent(): void
    {
        $data = $this->makeData(ipAddress: '192.168.1.1', userAgent: 'Mozilla/5.0');
        $evidence = $this->makeEvidence();

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $p) =>
                $p['ip_address'] === '192.168.1.1'
                && $p['user_agent'] === 'Mozilla/5.0'
            )
            ->andReturn($evidence);

        $this->service->record($data);
    }

    public function test_record_stores_declaration_flags(): void
    {
        $data = $this->makeData(aiGenerated: true, sponsored: true, affiliate: true);
        $evidence = $this->makeEvidence();

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $p) =>
                $p['ai_generated']      === true
                && $p['sponsored_content'] === true
                && $p['affiliate_content'] === true
            )
            ->andReturn($evidence);

        $this->service->record($data);
    }

    // ── hasEvidence() ────────────────────────────────────────────────────────

    public function test_has_evidence_returns_true_when_record_exists(): void
    {
        $evidence = $this->makeEvidence();

        $this->repository
            ->shouldReceive('findByCmsImageAndContributor')
            ->with(self::IMAGE_ID, self::USER_ID)
            ->andReturn($evidence);

        $this->assertTrue($this->service->hasEvidence(self::IMAGE_ID, self::USER_ID));
    }

    public function test_has_evidence_returns_false_when_no_record_found(): void
    {
        $this->repository
            ->shouldReceive('findByCmsImageAndContributor')
            ->with(self::IMAGE_ID, self::USER_ID)
            ->andReturn(null);

        $this->assertFalse($this->service->hasEvidence(self::IMAGE_ID, self::USER_ID));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeData(
        ?string $correlationId = null,
        ?string $ipAddress     = null,
        ?string $userAgent     = null,
        bool    $aiGenerated   = false,
        bool    $sponsored     = false,
        bool    $affiliate     = false,
    ): ImageEvidenceData {
        return new ImageEvidenceData(
            siteId:               self::SITE_ID,
            cmsImageId:           self::IMAGE_ID,
            contributorUserId:    self::USER_ID,
            imageRights:          OpenCollabImageRights::ContributorOwned,
            nameSubmitted:        'Summer garden',
            altTextSubmitted:     'A garden in bloom',
            creditSubmitted:      'Jane Smith',
            rightsConfirmation:   true,
            aiGenerated:          $aiGenerated,
            sponsoredContent:     $sponsored,
            affiliateContent:     $affiliate,
            requestCorrelationId: $correlationId,
            ipAddress:            $ipAddress,
            userAgent:            $userAgent,
        );
    }
}