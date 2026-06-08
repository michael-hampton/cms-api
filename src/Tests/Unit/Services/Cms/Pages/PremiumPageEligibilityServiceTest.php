<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Enums\Pages\PageStatus;
use App\Models\Page;
use App\Services\Cms\Pages\PremiumPageEligibilityService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PremiumPageEligibilityServiceTest extends TestCase
{
    private PremiumPageEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PremiumPageEligibilityService();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_passes_for_valid_waiting_approval_page(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => 'Premium Article',
            'status' => PageStatus::WAITING_APPROVAL->value,
            'contributor_id' => 10,
        ]);

        $result = $this->service->check($page, 599);

        $this->assertTrue($result->eligible);
        $this->assertSame([], $result->failures);
    }

    public function test_it_fails_when_page_has_no_contributor(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => 'Premium Article',
            'status' => PageStatus::WAITING_APPROVAL->value,
            'contributor_id' => null,
        ]);

        $result = $this->service->check($page, 599);

        $this->assertFalse($result->eligible);
        $this->assertContains(
            'Page must have a contributor before it can be approved as premium.',
            $result->failures
        );
    }

    public function test_it_fails_when_title_is_missing(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => '',
            'status' => PageStatus::WAITING_APPROVAL->value,
            'contributor_id' => 10,
        ]);

        $result = $this->service->check($page, 599);

        $this->assertFalse($result->eligible);
        $this->assertContains('Page title is required.', $result->failures);
    }

    public function test_it_fails_when_status_is_not_allowed(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => 'Premium Article',
            'status' => PageStatus::DRAFT->value,
            'contributor_id' => 10,
        ]);

        $result = $this->service->check($page, 599);

        $this->assertFalse($result->eligible);
        $this->assertStringContainsString(
            'cannot be approved as premium',
            implode(' ', $result->failures)
        );
    }

    public function test_it_fails_when_monetisation_is_disabled(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => 'Premium Article',
            'status' => PageStatus::WAITING_APPROVAL->value,
            'contributor_id' => 10,
            'monetisation_disabled_at' => '2026-06-07 12:00:00',
        ]);

        $result = $this->service->check($page, 599);

        $this->assertFalse($result->eligible);
        $this->assertContains('Page monetisation is disabled.', $result->failures);
    }

    public function test_it_fails_when_approved_price_is_zero(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => 'Premium Article',
            'status' => PageStatus::WAITING_APPROVAL->value,
            'contributor_id' => 10,
        ]);

        $result = $this->service->check($page, 0);

        $this->assertFalse($result->eligible);
        $this->assertContains('Approved premium price must be greater than zero.', $result->failures);
    }

    public function test_assert_eligible_throws_when_invalid(): void
    {
        $page = $this->mockPage([
            'id' => 1,
            'title' => '',
            'status' => PageStatus::DRAFT->value,
            'contributor_id' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page cannot be approved as premium');

        $this->service->assertEligible($page, 0);
    }

    /**
     * @param array<string, mixed> $attributes
     */
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