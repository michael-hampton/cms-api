<?php

namespace Tests\Unit\Services\Cms\Pages;

use App\Enums\Pages\PageStatus;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Repositories\Cms\Pages\PageMetadataRepository;
use App\Services\Cms\Pages\PremiumPagePurchaseEligibilityService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PremiumPagePurchaseEligibilityServiceTest extends TestCase
{
    private PageMetadataRepository $metadataRepository;
    private PremiumPagePurchaseEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadataRepository = Mockery::mock(PageMetadataRepository::class);

        $this->service = new PremiumPagePurchaseEligibilityService(
            $this->metadataRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_allows_purchasable_premium_page(): void
    {
        $page = $this->validPage();

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->service->assertPurchasable($page);

        $this->assertTrue(true);
    }

    public function test_it_blocks_unpublished_page(): void
    {
        $page = $this->validPage([
            'status' => PageStatus::DRAFT->value,
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page is not published.');

        $this->service->assertPurchasable($page);
    }

    public function test_it_blocks_page_that_is_not_paid(): void
    {
        $page = $this->validPage([
            'is_paid' => false,
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page is not marked as paid.');

        $this->service->assertPurchasable($page);
    }

    public function test_it_blocks_page_without_approved_price(): void
    {
        $page = $this->validPage([
            'price' => 0,
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('valid premium price');

        $this->service->assertPurchasable($page);
    }

    public function test_it_blocks_page_without_premium_approval(): void
    {
        $page = $this->validPage([
            'premium_approved_at' => null,
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not been approved');

        $this->service->assertPurchasable($page);
    }

    public function test_it_blocks_disabled_monetisation(): void
    {
        $page = $this->validPage([
            'monetisation_disabled_at' => '2026-06-07 12:00:00',
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('monetisation has been disabled');

        $this->service->assertPurchasable($page);
    }

    public function test_it_blocks_page_without_contributor(): void
    {
        $page = $this->validPage([
            'contributor_id' => null,
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have a contributor');

        $this->service->assertPurchasable($page);
    }

    public function test_it_blocks_when_metadata_visibility_is_not_premium(): void
    {
        $page = $this->validPage();

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'public',
            ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('visibility is not premium');

        $this->service->assertPurchasable($page);
    }

    public function test_is_purchasable_returns_false_when_invalid(): void
    {
        $page = $this->validPage([
            'is_paid' => false,
        ]);

        $this->metadataRepository
            ->shouldReceive('findByPageId')
            ->once()
            ->with(1)
            ->andReturn($this->mockPageMetadata([
                'page_id' => 1,
                'visibility' => 'premium',
            ]));

        $this->assertFalse($this->service->isPurchasable($page));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function validPage(array $overrides = []): Page&MockInterface
    {
        return $this->mockPage(array_merge([
            'id' => 1,
            'status' => PageStatus::PUBLISHED->value,
            'is_paid' => true,
            'price' => 599,
            'premium_approved_at' => '2026-06-07 12:00:00',
            'monetisation_disabled_at' => null,
            'contributor_id' => 7,
        ], $overrides));
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

    /**
     * @param array<string, mixed> $attributes
     */
    private function mockPageMetadata(array $attributes): PageMetadata&MockInterface
    {
        /** @var PageMetadata&MockInterface $metadata */
        $metadata = Mockery::mock(PageMetadata::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $metadata->{$key} = $value;
        }

        return $metadata;
    }
}