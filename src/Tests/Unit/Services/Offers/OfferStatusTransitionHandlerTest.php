<?php

namespace App\Tests\Unit\Services\Offers;

use App\Enums\OfferStatus;
use App\Framework\Date;
use App\Models\ProductOffer;
use App\Services\Offers\OfferStatusTransitionHandler;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OfferStatusTransitionHandlerTest extends TestCase
{
    private OfferStatusTransitionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new OfferStatusTransitionHandler();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ===================================================================
    // fillStatusFields() Tests
    // ===================================================================

    public function testFillStatusFieldsReturnsUnchangedDataWhenStatusNotSet(): void
    {
        $data = [
            'title' => 'Test Offer',
            'description' => 'Test Description'
        ];
        $userId = 1;

        $result = $this->handler->fillStatusFields($data, $userId);

        $this->assertEquals($data, $result);
        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);
    }

    public function testFillStatusFieldsAddsPublishedFieldsWhenStatusIsPublished(): void
    {
        $data = [
            'title' => 'Test Offer',
            'status' => OfferStatus::PUBLISHED->value
        ];
        $userId = 42;

        $result = $this->handler->fillStatusFields($data, $userId);

        $this->assertEquals($userId, $result['published_by']);
        $this->assertNotEmpty($result['published_at']);
        $this->assertInstanceOf(Date::class, $result['published_at']);

        // Original data should still be present
        $this->assertEquals('Test Offer', $result['title']);
        $this->assertEquals(OfferStatus::PUBLISHED->value, $result['status']);
    }

    public function testFillStatusFieldsAddsRejectedFieldsWhenStatusIsRejected(): void
    {
        $data = [
            'title' => 'Test Offer',
            'status' => OfferStatus::REJECTED->value
        ];
        $userId = 99;

        $result = $this->handler->fillStatusFields($data, $userId);

        $this->assertEquals($userId, $result['rejected_by']);
        $this->assertNotEmpty($result['rejected_at']);
        $this->assertInstanceOf(Date::class, $result['rejected_at']);


        // Should NOT have published fields
        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
    }

    public function testFillStatusFieldsDoesNotAddFieldsForDraftStatus(): void
    {
        $data = [
            'title' => 'Test Offer',
            'status' => OfferStatus::DRAFT->value
        ];
        $userId = 1;

        $result = $this->handler->fillStatusFields($data, $userId);

        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);

        // Original data should be unchanged
        $this->assertEquals('Test Offer', $result['title']);
        $this->assertEquals(OfferStatus::DRAFT->value, $result['status']);
    }

    public function testFillStatusFieldsDoesNotAddFieldsForExpiredStatus(): void
    {
        $data = [
            'title' => 'Test Offer',
            'status' => OfferStatus::EXPIRED->value
        ];
        $userId = 1;

        $result = $this->handler->fillStatusFields($data, $userId);

        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);
    }

    public function testFillStatusFieldsWorksWithZeroUserId(): void
    {
        $data = [
            'status' => OfferStatus::PUBLISHED->value
        ];
        $userId = 0;

        $result = $this->handler->fillStatusFields($data, $userId);

        $this->assertEquals(0, $result['published_by']);
        $this->assertNotEmpty($result['published_at']);
    }

    public function testFillStatusFieldsPreservesExistingDataFields(): void
    {
        $data = [
            'title' => 'Test Offer',
            'description' => 'Description',
            'price' => 99.99,
            'quantity' => 10,
            'status' => OfferStatus::PUBLISHED->value
        ];
        $userId = 1;

        $result = $this->handler->fillStatusFields($data, $userId);

        // All original fields should be preserved
        $this->assertEquals('Test Offer', $result['title']);
        $this->assertEquals('Description', $result['description']);
        $this->assertEquals(99.99, $result['price']);
        $this->assertEquals(10, $result['quantity']);

        // Plus new fields
        $this->assertArrayHasKey('published_by', $result);
        $this->assertArrayHasKey('published_at', $result);
    }

    // ===================================================================
    // fillStatusFieldsOnUpdate() Tests
    // ===================================================================

    public function testFillStatusFieldsOnUpdateReturnsUnchangedWhenStatusNotSet(): void
    {
        $data = [
            'title' => 'Updated Title'
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $userId = 1;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        $this->assertEquals($data, $result);
    }

    public function testFillStatusFieldsOnUpdateReturnsUnchangedWhenStatusUnchanged(): void
    {
        $data = [
            'title' => 'Updated Title',
            'status' => OfferStatus::DRAFT->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $userId = 1;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        $this->assertEquals($data, $result);
        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
    }

    public function testFillStatusFieldsOnUpdateAddsPublishedFieldsOnFirstPublish(): void
    {
        $data = [
            'status' => OfferStatus::PUBLISHED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->published_at = null; // Never published before
        $userId = 42;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        $this->assertEquals($userId, $result['published_by']);
        $this->assertNotEmpty($result['published_at']);
        $this->assertInstanceOf(Date::class, $result['published_at']);
    }

    public function testFillStatusFieldsOnUpdateDoesNotOverwriteExistingPublishedAt(): void
    {
        $originalPublishedAt = '2024-01-01 10:00:00';

        $data = [
            'status' => OfferStatus::PUBLISHED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->published_at = $originalPublishedAt; // Already published before
        $userId = 42;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Should NOT add published_by or published_at
        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);

        // Only status should be present
        $this->assertEquals(OfferStatus::PUBLISHED->value, $result['status']);
    }

    public function testFillStatusFieldsOnUpdateAddsRejectedFieldsOnFirstRejection(): void
    {
        $data = [
            'status' => OfferStatus::REJECTED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->rejected_at = null; // Never rejected before
        $userId = 99;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        $this->assertEquals($userId, $result['rejected_by']);
        $this->assertNotEmpty($result['rejected_at']);
        $this->assertInstanceOf(Date::class, $result['rejected_at']);
    }

    public function testFillStatusFieldsOnUpdateDoesNotOverwriteExistingRejectedAt(): void
    {
        $originalRejectedAt = '2024-01-01 12:00:00';

        $data = [
            'status' => OfferStatus::REJECTED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->rejected_at = $originalRejectedAt; // Already rejected before
        $userId = 99;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Should NOT add rejected_by or rejected_at
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);
    }

    public function testFillStatusFieldsOnUpdateHandlesTransitionFromPublishedToDraft(): void
    {
        $data = [
            'status' => OfferStatus::DRAFT->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::PUBLISHED->value;
        $currentOffer->published_at = '2024-01-01 10:00:00';
        $userId = 1;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Should only have status, no timestamp fields added
        $this->assertEquals(OfferStatus::DRAFT->value, $result['status']);
        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);
    }

    public function testFillStatusFieldsOnUpdateHandlesTransitionFromRejectedToPublished(): void
    {
        $data = [
            'status' => OfferStatus::PUBLISHED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::REJECTED->value;
        $currentOffer->published_at = null;
        $currentOffer->rejected_at = '2024-01-01 12:00:00';
        $userId = 42;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Should add published fields (first time published)
        $this->assertEquals($userId, $result['published_by']);
        $this->assertNotEmpty($result['published_at']);

        // Should NOT add rejected fields (already set)
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);
    }

    public function testFillStatusFieldsOnUpdatePreservesOtherDataFields(): void
    {
        $data = [
            'title' => 'Updated Offer',
            'price' => 149.99,
            'status' => OfferStatus::PUBLISHED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->published_at = null;
        $userId = 1;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Original fields preserved
        $this->assertEquals('Updated Offer', $result['title']);
        $this->assertEquals(149.99, $result['price']);
        $this->assertEquals(OfferStatus::PUBLISHED->value, $result['status']);

        // New fields added
        $this->assertArrayHasKey('published_by', $result);
        $this->assertArrayHasKey('published_at', $result);
    }

    public function testFillStatusFieldsOnUpdateWithNullCurrentOffer(): void
    {
        // Edge case: what if currentOffer is somehow null?
        // This should not happen in practice, but let's test defensive programming

        $data = [
            'status' => OfferStatus::PUBLISHED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = null;
        $currentOffer->published_at = null;
        $userId = 1;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Should add published fields because published_at is null
        $this->assertArrayHasKey('published_by', $result);
        $this->assertArrayHasKey('published_at', $result);
    }

    public function testFillStatusFieldsOnUpdateDoesNotAffectDraftToExpiredTransition(): void
    {
        $data = [
            'status' => OfferStatus::EXPIRED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->published_at = null;
        $currentOffer->rejected_at = null;
        $userId = 1;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        // Should only have status
        $this->assertEquals(OfferStatus::EXPIRED->value, $result['status']);
        $this->assertArrayNotHasKey('published_by', $result);
        $this->assertArrayNotHasKey('published_at', $result);
        $this->assertArrayNotHasKey('rejected_by', $result);
        $this->assertArrayNotHasKey('rejected_at', $result);
    }

    public function testFillStatusFieldsOnUpdateWorksWithZeroUserId(): void
    {
        $data = [
            'status' => OfferStatus::PUBLISHED->value
        ];

        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->published_at = null;
        $userId = 0;

        $result = $this->handler->fillStatusFieldsOnUpdate($data, $currentOffer, $userId);

        $this->assertEquals(0, $result['published_by']);
        $this->assertNotEmpty($result['published_at']);
    }

    // ===================================================================
    // Edge Cases & Integration Tests
    // ===================================================================

    public function testBothMethodsProduceSameResultForInitialPublish(): void
    {
        $userId = 42;

        // Test fillStatusFields
        $data1 = ['status' => OfferStatus::PUBLISHED->value];
        $result1 = $this->handler->fillStatusFields($data1, $userId);

        // Test fillStatusFieldsOnUpdate with never-published offer
        $data2 = ['status' => OfferStatus::PUBLISHED->value];
        $currentOffer = m::mock(ProductOffer::class)->makePartial();
        $currentOffer->status = OfferStatus::DRAFT->value;
        $currentOffer->published_at = null;
        $result2 = $this->handler->fillStatusFieldsOnUpdate($data2, $currentOffer, $userId);

        // Both should have published_by and published_at
        $this->assertEquals($userId, $result1['published_by']);
        $this->assertEquals($userId, $result2['published_by']);
        $this->assertArrayHasKey('published_at', $result1);
        $this->assertArrayHasKey('published_at', $result2);
    }

    public function testEnumValidationThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(\ValueError::class);

        $data = [
            'status' => 'invalid_status'
        ];
        $userId = 1;

        $this->handler->fillStatusFields($data, $userId);
    }

    public function testDatetimeFormatIsConsistent(): void
    {
        $userId = 1;

        // Test published
        $result1 = $this->handler->fillStatusFields(
            ['status' => OfferStatus::PUBLISHED->value],
            $userId
        );

        // Test rejected
        $result2 = $this->handler->fillStatusFields(
            ['status' => OfferStatus::REJECTED->value],
            $userId
        );

        $this->assertInstanceOf(Date::class, $result1['published_at']);
        $this->assertInstanceOf(Date::class, $result2['rejected_at']);
    }

    public function testMultipleStatusTransitionsInSequence(): void
    {
        $userId1 = 1;
        $userId2 = 2;

        // 1. Draft → Published (first time)
        $offer = m::mock(ProductOffer::class)->makePartial();
        $offer->status = OfferStatus::DRAFT->value;
        $offer->published_at = null;
        $offer->rejected_at = null;

        $data1 = ['status' => OfferStatus::PUBLISHED->value];
        $result1 = $this->handler->fillStatusFieldsOnUpdate($data1, $offer, $userId1);

        $this->assertEquals($userId1, $result1['published_by']);
        $this->assertArrayHasKey('published_at', $result1);

        // 2. Published → Rejected
        $offer->status = OfferStatus::PUBLISHED->value;
        $offer->published_at = $result1['published_at'];
        $offer->rejected_at = null;

        $data2 = ['status' => OfferStatus::REJECTED->value];
        $result2 = $this->handler->fillStatusFieldsOnUpdate($data2, $offer, $userId2);

        $this->assertEquals($userId2, $result2['rejected_by']);
        $this->assertArrayHasKey('rejected_at', $result2);
        $this->assertArrayNotHasKey('published_by', $result2); // Should not re-set

        // 3. Rejected → Published (re-publish)
        $offer->status = OfferStatus::REJECTED->value;
        $offer->published_at = $result1['published_at']; // Still has original
        $offer->rejected_at = $result2['rejected_at'];

        $data3 = ['status' => OfferStatus::PUBLISHED->value];
        $result3 = $this->handler->fillStatusFieldsOnUpdate($data3, $offer, $userId1);

        // Should NOT re-set published fields
        $this->assertArrayNotHasKey('published_by', $result3);
        $this->assertArrayNotHasKey('published_at', $result3);
    }
}