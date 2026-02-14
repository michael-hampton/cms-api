<?php

namespace App\Tests\Unit\Services\Reviews;

use App\Repositories\ReviewRepository;
use App\Services\Reviews\ReviewQueryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReviewQueryServiceTest extends TestCase
{
    private $reviewRepository;
    private ReviewQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reviewRepository = Mockery::mock(ReviewRepository::class);
        $this->service = new ReviewQueryService($this->reviewRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetPaginatedProductReviews()
    {
        $mockReview = Mockery::mock(\App\Models\Review::class);
        $mockReview->shouldAllowMockingProtectedMethods();
        $mockReview->shouldReceive('relationLoaded')->andReturn(false);
        $mockReview->shouldReceive('hasGetMutator')->andReturn(false);
        $mockReview->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $mockReview->shouldReceive('getAttribute')->with('rating')->andReturn(5);
        $mockReview->shouldReceive('getAttribute')->with('title')->andReturn('Great');
        $mockReview->shouldReceive('getAttribute')->with('comment')->andReturn('Loved it');
        $mockReview->shouldReceive('getAttribute')->with('author_name')->andReturn('John Doe');
        $mockReview->shouldReceive('getAttribute')->with('is_verified_purchase')->andReturn(true);
        $mockReview->shouldReceive('getAttribute')->with('helpful_count')->andReturn(5);
        $mockReview->shouldReceive('getAttribute')->with('unhelpful_count')->andReturn(1);
        $mockReview->shouldReceive('getAttribute')->with('formatted_date')->andReturn('Yesterday');
        $mockReview->shouldReceive('getAttribute')->with('created_at')->andReturn('2024-01-01');

        $collection = collect([$mockReview]);

        $this->reviewRepository->shouldReceive('findByProduct')
            ->once()
            ->with(1, 1, 10)
            ->andReturn([
                'data' => $collection,
                'pagination' => ['total' => 1, 'current_page' => 1]
            ]);

        $result = $this->service->getPaginatedProductReviews(1, 1, 10);

        $this->assertArrayHasKey('reviews', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['reviews']);
        $this->assertEquals(1, $result['reviews'][0]['id']);
        $this->assertEquals('Great', $result['reviews'][0]['title']);
    }

    public function testGetPaginatedProductReviewsWithMultiple()
    {
        $review1 = Mockery::mock(\App\Models\Review::class);
        $review1->shouldAllowMockingProtectedMethods();
        $review1->shouldReceive('relationLoaded')->andReturn(false);
        $review1->shouldReceive('hasGetMutator')->andReturn(false);
        $review1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $review1->shouldReceive('getAttribute')->with('rating')->andReturn(5);
        $review1->shouldReceive('getAttribute')->with('title')->andReturn('Great');
        $review1->shouldReceive('getAttribute')->with('comment')->andReturn('Loved it');
        $review1->shouldReceive('getAttribute')->with('author_name')->andReturn('John');
        $review1->shouldReceive('getAttribute')->with('is_verified_purchase')->andReturn(true);
        $review1->shouldReceive('getAttribute')->with('helpful_count')->andReturn(5);
        $review1->shouldReceive('getAttribute')->with('unhelpful_count')->andReturn(1);
        $review1->shouldReceive('getAttribute')->with('formatted_date')->andReturn('Yesterday');
        $review1->shouldReceive('getAttribute')->with('created_at')->andReturn('2024-01-01');

        $review2 = Mockery::mock(\App\Models\Review::class);
        $review2->shouldAllowMockingProtectedMethods();
        $review2->shouldReceive('relationLoaded')->andReturn(false);
        $review2->shouldReceive('hasGetMutator')->andReturn(false);
        $review2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $review2->shouldReceive('getAttribute')->with('rating')->andReturn(4);
        $review2->shouldReceive('getAttribute')->with('title')->andReturn('Good');
        $review2->shouldReceive('getAttribute')->with('comment')->andReturn('Nice');
        $review2->shouldReceive('getAttribute')->with('author_name')->andReturn('Jane');
        $review2->shouldReceive('getAttribute')->with('is_verified_purchase')->andReturn(false);
        $review2->shouldReceive('getAttribute')->with('helpful_count')->andReturn(3);
        $review2->shouldReceive('getAttribute')->with('unhelpful_count')->andReturn(0);
        $review2->shouldReceive('getAttribute')->with('formatted_date')->andReturn('2 days ago');
        $review2->shouldReceive('getAttribute')->with('created_at')->andReturn('2024-01-02');

        $collection = collect([$review1, $review2]);

        $this->reviewRepository->shouldReceive('findByProduct')
            ->once()
            ->andReturn([
                'data' => $collection,
                'pagination' => ['total' => 2]
            ]);

        $result = $this->service->getPaginatedProductReviews(1);

        $this->assertCount(2, $result['reviews']);
    }

    public function testGetReviewSummary()
    {
        $this->reviewRepository->shouldReceive('getAverageRating')
            ->once()
            ->with(1)
            ->andReturn(4.5);

        $this->reviewRepository->shouldReceive('getTotalReviewCount')
            ->once()
            ->with(1)
            ->andReturn(100);

        $this->reviewRepository->shouldReceive('getRatingBreakdown')
            ->once()
            ->with(1)
            ->andReturn([5 => 60, 4 => 20, 3 => 10, 2 => 5, 1 => 5]);

        $result = $this->service->getReviewSummary(1);

        $this->assertEquals(4.5, $result->averageRating);
        $this->assertEquals(100, $result->totalReviews);
        $this->assertArrayHasKey(5, $result->ratingBreakdown);
        $this->assertEquals(60.0, $result->ratingPercentages[5]);
        $this->assertEquals(20.0, $result->ratingPercentages[4]);
    }

    public function testGetReviewSummaryWithNoReviews()
    {
        $this->reviewRepository->shouldReceive('getAverageRating')->once()->andReturn(0.0);
        $this->reviewRepository->shouldReceive('getTotalReviewCount')->once()->andReturn(0);
        $this->reviewRepository->shouldReceive('getRatingBreakdown')
            ->once()
            ->andReturn([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);

        $result = $this->service->getReviewSummary(1);

        $this->assertEquals(0.0, $result->averageRating);
        $this->assertEquals(0, $result->totalReviews);
        $this->assertEquals(0.0, $result->ratingPercentages[5]);
    }

    public function testGetReviewSummaryCalculatesPercentagesCorrectly()
    {
        $this->reviewRepository->shouldReceive('getAverageRating')->once()->andReturn(4.0);
        $this->reviewRepository->shouldReceive('getTotalReviewCount')->once()->andReturn(10);
        $this->reviewRepository->shouldReceive('getRatingBreakdown')
            ->once()
            ->andReturn([5 => 5, 4 => 3, 3 => 1, 2 => 1, 1 => 0]);

        $result = $this->service->getReviewSummary(1);

        $this->assertEquals(50.0, $result->ratingPercentages[5]);
        $this->assertEquals(30.0, $result->ratingPercentages[4]);
        $this->assertEquals(10.0, $result->ratingPercentages[3]);
        $this->assertEquals(10.0, $result->ratingPercentages[2]);
        $this->assertEquals(0.0, $result->ratingPercentages[1]);
    }

    public function testCanUserReviewWhenNotLoggedIn()
    {
        $result = $this->service->canUserReview(1, null);

        $this->assertFalse($result['can_review']);
        $this->assertEquals('You must be logged in to submit a review', $result['reason']);
    }

    public function testCanUserReviewWhenAlreadyReviewed()
    {
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')
            ->once()
            ->with(1, 123)
            ->andReturn(true);

        $result = $this->service->canUserReview(1, 123);

        $this->assertFalse($result['can_review']);
        $this->assertEquals('You have already reviewed this product', $result['reason']);
    }

    public function testCanUserReviewWhenAllowed()
    {
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')
            ->once()
            ->with(1, 123)
            ->andReturn(false);

        $result = $this->service->canUserReview(1, 123);

        $this->assertTrue($result['can_review']);
        $this->assertNull($result['reason']);
    }
}