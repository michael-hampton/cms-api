<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Enums\Pages\PageStatus;
use App\Events\Cms\ContentSubmittedForApproval;
use App\Events\OpenCollab\PremiumMonetisationApprovedEvent;
use App\Events\OpenCollab\PremiumMonetisationRejectedEvent;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\PageHistoryService;
use App\Services\Cms\Pages\PremiumPageApprovalService;
use App\Services\Cms\Pages\PremiumPageEligibilityService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PremiumPageApprovalServiceTest extends TestCase
{
    private PageRepository $pageRepository;
    private PageMetadataRepository $metadataRepository;
    private PremiumPageEligibilityService $eligibilityService;
    private PageHistoryService $historyService;
    private PremiumPageApprovalService $service;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->metadataRepository = Mockery::mock(PageMetadataRepository::class);
        $this->eligibilityService = Mockery::mock(PremiumPageEligibilityService::class);
        $this->historyService = Mockery::mock(PageHistoryService::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);

        $this->service = new PremiumPageApprovalService(
            $this->pageRepository,
            $this->metadataRepository,
            $this->eligibilityService,
            $this->historyService,
            $this->eventDispatcher
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_approve_premium_sets_paid_price_approval_fields_and_visibility(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'title' => 'Premium Article',
            'status' => PageStatus::WAITING_APPROVAL->value,
            'contributor_id' => 7,
            'is_paid' => false,
            'price' => null,
        ]);

        $updated = $this->mockPage([
            'id' => 123,
            'is_paid' => true,
            'price' => 599,
            'premium_approved_by' => 99,
        ]);

        $this->eligibilityService
            ->shouldReceive('assertEligible')
            ->once()
            ->with($page, 599);

        $this->pageRepository
            ->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function (array $data): bool {
                return $data['is_paid'] === true
                    && $data['price'] === 599
                    && $data['premium_approved_by'] === 99
                    && $data['premium_approval_note'] === 'Good exclusive article'
                    && $data['premium_rejected_at'] === null
                    && $data['monetisation_disabled_at'] === null
                    && !empty($data['premium_approved_at']);
            }));

        $this->metadataRepository
            ->shouldReceive('createOrUpdate')
            ->once()
            ->with(123, ['visibility' => 'premium']);

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($updated);

        $this->historyService
            ->shouldReceive('logPageAction')
            ->once()
            ->withArgs(function (
                int $pageId,
                string $action,
                ?string $description,
                ?array $changes,
                bool $includeSnapshot
            ): bool {
                return $pageId === 123
                    && $action === 'premium_approved'
                    && $description === 'Premium monetisation approved'
                    && $changes['price']['new'] === 599
                    && $changes['premium_approved_by'] === 99
                    && $includeSnapshot === true;
            });

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event): bool {
                return $event instanceof PremiumMonetisationApprovedEvent
                    && (int) $event->adminId === 99;
            });

        $result = $this->service->approvePremium($page, 99, 599, 'Good exclusive article');

        $this->assertSame($updated, $result);
    }

    public function test_approve_free_clears_paid_price_and_sets_public_visibility(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'is_paid' => true,
            'price' => 599,
        ]);

        $updated = $this->mockPage([
            'id' => 123,
            'is_paid' => false,
            'price' => null,
        ]);

        $this->pageRepository
            ->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function (array $data): bool {
                return $data['is_paid'] === false
                    && $data['price'] === null
                    && $data['premium_approved_at'] === null
                    && $data['premium_approved_by'] === null;
            }));

        $this->metadataRepository
            ->shouldReceive('createOrUpdate')
            ->once()
            ->with(123, ['visibility' => 'free']);

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($updated);

        $this->historyService
            ->shouldReceive('logPageAction')
            ->once()
            ->withArgs(function (
                int $pageId,
                string $action,
                ?string $description,
                ?array $changes,
                bool $includeSnapshot
            ): bool {
                return $pageId === 123
                    && $action === 'premium_marked_free'
                    && $description === 'Page approved as free content'
                    && $changes['is_paid']['new'] === false
                    && $includeSnapshot === true;
            });

        $result = $this->service->approveFree($page, 99, 'Publish free');

        $this->assertSame($updated, $result);
    }

    public function test_reject_premium_clears_paid_price_records_rejection_and_sets_public_visibility(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'is_paid' => true,
            'price' => 599,
        ]);

        $updated = $this->mockPage([
            'id' => 123,
            'is_paid' => false,
            'price' => null,
            'premium_rejected_by' => 99,
        ]);

        $this->pageRepository
            ->shouldReceive('update')
            ->once()
            ->with(123, Mockery::on(function (array $data): bool {
                return $data['is_paid'] === false
                    && $data['price'] === null
                    && $data['premium_rejected_by'] === 99
                    && $data['premium_rejection_reason'] === 'Not suitable'
                    && $data['premium_approved_at'] === null
                    && $data['premium_approved_by'] === null
                    && !empty($data['premium_rejected_at']);
            }));

        $this->metadataRepository
            ->shouldReceive('createOrUpdate')
            ->once()
            ->with(123, ['visibility' => 'free']);

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($updated);

        $this->historyService
            ->shouldReceive('logPageAction')
            ->once()
            ->withArgs(function (
                int $pageId,
                string $action,
                ?string $description,
                ?array $changes,
                bool $includeSnapshot
            ): bool {
                return $pageId === 123
                    && $action === 'premium_rejected'
                    && $description === 'Premium monetisation rejected'
                    && $changes['premium_rejection_reason'] === 'Not suitable'
                    && $includeSnapshot === true;
            });

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event): bool {
                return $event instanceof PremiumMonetisationRejectedEvent
                    && (int) $event->adminId === 99
                    && $event->reason === 'Not suitable';
            });

        $result = $this->service->rejectPremium($page, 99, 'Not suitable');

        $this->assertSame($updated, $result);
    }

    public function test_approve_premium_rejects_zero_price(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'contributor_id' => 7,
        ]);

        $this->eligibilityService
            ->shouldNotReceive('assertEligible');

        $this->pageRepository
            ->shouldNotReceive('update');

        $this->metadataRepository
            ->shouldNotReceive('createOrUpdate');

        $this->historyService
            ->shouldNotReceive('logPageAction');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Approved price must be greater than zero.');

        $this->service->approvePremium($page, 99, 0);
    }

    public function test_approve_premium_stops_when_eligibility_fails(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'title' => 'Premium Article',
            'contributor_id' => 7,
            'is_paid' => false,
            'price' => null,
        ]);

        $this->eligibilityService
            ->shouldReceive('assertEligible')
            ->once()
            ->with($page, 599)
            ->andThrow(new \InvalidArgumentException('Page cannot be approved as premium.'));

        $this->pageRepository
            ->shouldNotReceive('update');

        $this->metadataRepository
            ->shouldNotReceive('createOrUpdate');

        $this->pageRepository
            ->shouldNotReceive('find');

        $this->historyService
            ->shouldNotReceive('logPageAction');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page cannot be approved as premium.');

        $this->service->approvePremium($page, 99, 599);
    }

    public function test_reject_premium_requires_reason(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'is_paid' => true,
            'price' => 599,
        ]);

        $this->pageRepository
            ->shouldNotReceive('update');

        $this->metadataRepository
            ->shouldNotReceive('createOrUpdate');

        $this->pageRepository
            ->shouldNotReceive('find');

        $this->historyService
            ->shouldNotReceive('logPageAction');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A rejection reason is required.');

        $this->service->rejectPremium($page, 99, '   ');
    }

    public function test_approve_premium_throws_when_updated_page_cannot_be_reloaded(): void
    {
        $page = $this->mockPage([
            'id' => 123,
            'site_id' => 1,
            'title' => 'Premium Article',
            'contributor_id' => 7,
            'is_paid' => false,
            'price' => null,
        ]);

        $this->eligibilityService
            ->shouldReceive('assertEligible')
            ->once()
            ->with($page, 599);

        $this->pageRepository
            ->shouldReceive('update')
            ->once()
            ->with(123, Mockery::type('array'));

        $this->metadataRepository
            ->shouldReceive('createOrUpdate')
            ->once()
            ->with(123, ['visibility' => 'premium']);

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn(null);

        $this->historyService
            ->shouldNotReceive('logPageAction');

        $this->eventDispatcher
            ->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Updated page [123] could not be loaded.');

        $this->service->approvePremium($page, 99, 599);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockPage(array $attributes): Page&MockInterface
    {
        /** @var Page&MockInterface $page */
        $page = Mockery::mock(Page::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }
}