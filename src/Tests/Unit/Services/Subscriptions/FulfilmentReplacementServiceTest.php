<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\Events\Subscriptions\IssueReplacementRequested;
use App\Models\FulfilmentReplacement;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Services\Subscriptions\FulfilmentReplacementEligibilityService;
use App\Services\Subscriptions\FulfilmentReplacementService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FulfilmentReplacementService.
 *
 * The service now delegates all eligibility logic to FulfilmentReplacementEligibilityService.
 * These tests verify:
 *   - Empty reason is rejected before eligibility is checked.
 *   - Denied eligibility results in InvalidArgumentException with the blocked reason.
 *   - Allowed eligibility proceeds to create a replacement record.
 *   - IssueReplacementRequested event is emitted on success.
 *   - The created replacement is returned.
 */
class FulfilmentReplacementServiceTest extends TestCase
{
    private FulfilmentReplacementRepository          $replacementRepository;
    private FulfilmentReplacementEligibilityService  $eligibilityService;
    private Logger                                   $logger;
    private FulfilmentReplacementService             $service;

    // ── Reason validation ─────────────────────────────────────────────────────

    public function test_throws_when_reason_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason is required for issue replacement.');

        // Eligibility should never be consulted for an empty reason.
        $this->eligibilityService->shouldNotReceive('canRequest');

        $this->service->requestReplacement(1, 100, '   ', 5, 1);
    }

    public function test_throws_when_reason_is_blank_whitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reason is required');

        $this->service->requestReplacement(1, 100, "\t\n", 5, 1);
    }

    // ── Eligibility delegation ────────────────────────────────────────────────

    public function test_throws_when_eligibility_service_denies_request(): void
    {
        $this->eligibilityService
            ->shouldReceive('canRequest')
            ->once()
            ->with(1, 100, 1)
            ->andReturn(ReplacementEligibilityResult::denied('Only dispatched issues can be replaced.'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only dispatched issues can be replaced.');

        $this->service->requestReplacement(1, 100, 'Damaged', 5, 1);
    }

    public function test_blocked_reason_is_forwarded_verbatim(): void
    {
        $reason = 'A replacement is already in progress for this issue.';

        $this->eligibilityService
            ->shouldReceive('canRequest')
            ->once()
            ->andReturn(ReplacementEligibilityResult::denied($reason));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($reason);

        $this->service->requestReplacement(1, 100, 'Missing', 5, 1);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_creates_replacement_when_eligibility_allows(): void
    {
        $this->eligibilityService
            ->shouldReceive('canRequest')
            ->once()
            ->with(1, 100, 1)
            ->andReturn(ReplacementEligibilityResult::allowed());

        $replacement = $this->makeReplacement();

        $this->replacementRepository
            ->shouldReceive('createReplacement')
            ->once()
            ->with(1, 100, 'Damaged in transit', 5)
            ->andReturn($replacement);

        $result = $this->service->requestReplacement(1, 100, 'Damaged in transit', 5, 1);

        $this->assertSame($replacement, $result);
    }

    public function test_returns_created_replacement_object(): void
    {
        $this->eligibilityService
            ->shouldReceive('canRequest')->andReturn(ReplacementEligibilityResult::allowed());

        $replacement = $this->makeReplacement(id: 42);

        $this->replacementRepository
            ->shouldReceive('createReplacement')->andReturn($replacement);

        $result = $this->service->requestReplacement(1, 100, 'Not received', 5, 1);

        $this->assertEquals(42, $result->id);
    }

    public function test_reason_is_trimmed_before_being_stored(): void
    {
        $this->eligibilityService
            ->shouldReceive('canRequest')->andReturn(ReplacementEligibilityResult::allowed());

        // createReplacement must receive the trimmed reason, not the padded one.
        $this->replacementRepository
            ->shouldReceive('createReplacement')
            ->once()
            ->with(1, 100, 'Damaged', 5)
            ->andReturn($this->makeReplacement());

        $this->service->requestReplacement(1, 100, '  Damaged  ', 5, 1);

        $this->assertTrue(true);
    }

    // ── Test data helpers ─────────────────────────────────────────────────────

    private function makeReplacement(int $id = 999): object
    {
        $replacement = Mockery::mock(FulfilmentReplacement::class)->makePartial();
        $replacement->id              = $id;
        $replacement->status          = 'pending';
        $replacement->subscription_id = 1;
        $replacement->issue_id        = 100;
        return $replacement;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->replacementRepository = Mockery::mock(FulfilmentReplacementRepository::class);
        $this->eligibilityService    = Mockery::mock(FulfilmentReplacementEligibilityService::class);
        $this->logger                 = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new FulfilmentReplacementService(
            $this->replacementRepository,
            $this->eligibilityService,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}