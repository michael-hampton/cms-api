<?php

namespace App\Tests\Unit\Services\Reviews;

use App\Models\SubscriptionPlan;
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
        $mockReview->shouldReceive('getAttribute')->with('created_at')->andReturn(now_datetime());

        $collection = collect([$mockReview]);

        $this->reviewRepository->shouldReceive('findByProduct')
            ->once()->with(1, 1, 10)
            ->andReturn(['data' => $collection, 'pagination' => ['total' => 1, 'current_page' => 1]]);

        $result = $this->service->getPaginatedProductReviews(1, 1, 10);

        $this->assertArrayHasKey('reviews', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['reviews']);
        $this->assertEquals(1, $result['reviews'][0]['id']);
        $this->assertEquals('Great', $result['reviews'][0]['title']);
    }

    public function testGetPaginatedProductReviewsWithMultiple()
    {
        $makeReview = fn(int $id, int $rating, string $title) => tap(
            Mockery::mock(\App\Models\Review::class),
            function ($m) use ($id, $rating, $title) {
                $m->shouldAllowMockingProtectedMethods();
                $m->shouldReceive('relationLoaded')->andReturn(false);
                $m->shouldReceive('hasGetMutator')->andReturn(false);
                $m->shouldReceive('getAttribute')->with('id')->andReturn($id);
                $m->shouldReceive('getAttribute')->with('rating')->andReturn($rating);
                $m->shouldReceive('getAttribute')->with('title')->andReturn($title);
                $m->shouldReceive('getAttribute')->with('comment')->andReturn('Nice');
                $m->shouldReceive('getAttribute')->with('author_name')->andReturn('User');
                $m->shouldReceive('getAttribute')->with('is_verified_purchase')->andReturn(false);
                $m->shouldReceive('getAttribute')->with('helpful_count')->andReturn(0);
                $m->shouldReceive('getAttribute')->with('unhelpful_count')->andReturn(0);
                $m->shouldReceive('getAttribute')->with('formatted_date')->andReturn('Today');
                $m->shouldReceive('getAttribute')->with('created_at')->andReturn(now_datetime());
            }
        );

        $collection = collect([$makeReview(1, 5, 'Great'), $makeReview(2, 4, 'Good')]);

        $this->reviewRepository->shouldReceive('findByProduct')
            ->once()->andReturn(['data' => $collection, 'pagination' => ['total' => 2]]);

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

    public function testGetPaginatedPlanReviews()
    {
        $mockReview = Mockery::mock(\App\Models\Review::class);
        $mockReview->shouldAllowMockingProtectedMethods();
        $mockReview->shouldReceive('relationLoaded')->andReturn(false);
        $mockReview->shouldReceive('hasGetMutator')->andReturn(false);
        $mockReview->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $mockReview->shouldReceive('getAttribute')->with('rating')->andReturn(5);
        $mockReview->shouldReceive('getAttribute')->with('title')->andReturn('Great plan');
        $mockReview->shouldReceive('getAttribute')->with('comment')->andReturn('Worth it');
        $mockReview->shouldReceive('getAttribute')->with('author_name')->andReturn('Jane Doe');
        $mockReview->shouldReceive('getAttribute')->with('is_verified_purchase')->andReturn(false);
        $mockReview->shouldReceive('getAttribute')->with('helpful_count')->andReturn(2);
        $mockReview->shouldReceive('getAttribute')->with('unhelpful_count')->andReturn(0);
        $mockReview->shouldReceive('getAttribute')->with('formatted_date')->andReturn('Today');
        $mockReview->shouldReceive('getAttribute')->with('created_at')->andReturn(now_datetime());

        $collection = collect([$mockReview]);

        $this->reviewRepository->shouldReceive('findByReviewable')
            ->once()
            ->with(SubscriptionPlan::class, 10, 1, 10)
            ->andReturn(['data' => $collection, 'pagination' => ['total' => 1, 'current_page' => 1]]);

        $result = $this->service->getPaginatedPlanReviews(10, 1, 10);

        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(1, $result['reviews']);
        $this->assertEquals('Great plan', $result['reviews'][0]['title']);
    }

    public function testGetPlanReviewSummary()
    {
        $this->reviewRepository->shouldReceive('getAverageRatingForReviewable')
            ->once()->with(SubscriptionPlan::class, 10)->andReturn(4.8);
        $this->reviewRepository->shouldReceive('getTotalReviewCountForReviewable')
            ->once()->with(SubscriptionPlan::class, 10)->andReturn(50);
        $this->reviewRepository->shouldReceive('getRatingBreakdownForReviewable')
            ->once()->with(SubscriptionPlan::class, 10)
            ->andReturn([5 => 40, 4 => 5, 3 => 3, 2 => 1, 1 => 1]);

        $result = $this->service->getPlanReviewSummary(10);

        $this->assertEquals(4.8, $result->averageRating);
        $this->assertEquals(50, $result->totalReviews);
        $this->assertEquals(80.0, $result->ratingPercentages[5]);
        $this->assertEquals(10.0, $result->ratingPercentages[4]);
    }

    public function testGetPlanReviewSummaryWithNoReviews()
    {
        $this->reviewRepository->shouldReceive('getAverageRatingForReviewable')
            ->once()->andReturn(0.0);
        $this->reviewRepository->shouldReceive('getTotalReviewCountForReviewable')
            ->once()->andReturn(0);
        $this->reviewRepository->shouldReceive('getRatingBreakdownForReviewable')
            ->once()->andReturn([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);

        $result = $this->service->getPlanReviewSummary(10);

        $this->assertEquals(0.0, $result->averageRating);
        $this->assertEquals(0, $result->totalReviews);
        foreach ([5, 4, 3, 2, 1] as $r) {
            $this->assertEquals(0.0, $result->ratingPercentages[$r]);
        }
    }

    public function testCanUserReviewPlanWhenNotLoggedIn()
    {
        $result = $this->service->canUserReviewPlan(10, null);
        $this->assertFalse($result['can_review']);
        $this->assertEquals('You must be logged in to submit a review', $result['reason']);
    }

    public function testCanUserReviewPlanWhenAlreadyReviewed()
    {
        $this->reviewRepository->shouldReceive('hasUserReviewedReviewable')
            ->once()
            ->with(SubscriptionPlan::class, 10, 123)
            ->andReturn(true);

        $result = $this->service->canUserReviewPlan(10, 123);
        $this->assertFalse($result['can_review']);
        $this->assertEquals('You have already reviewed this plan', $result['reason']);
    }

    public function testCanUserReviewPlanWhenAllowed()
    {
        $this->reviewRepository->shouldReceive('hasUserReviewedReviewable')
            ->once()
            ->with(SubscriptionPlan::class, 10, 123)
            ->andReturn(false);

        $result = $this->service->canUserReviewPlan(10, 123);
        $this->assertTrue($result['can_review']);
        $this->assertNull($result['reason']);
    }

    public function testPlanAndProductQueryMethodsAreIndependent()
    {
        // Product summary should call product-scoped repository methods
        $this->reviewRepository->shouldReceive('getAverageRating')->once()->with(1)->andReturn(3.0);
        $this->reviewRepository->shouldReceive('getTotalReviewCount')->once()->with(1)->andReturn(5);
        $this->reviewRepository->shouldReceive('getRatingBreakdown')->once()->with(1)
            ->andReturn([5 => 1, 4 => 1, 3 => 1, 2 => 1, 1 => 1]);

        // Plan summary should call polymorphic repository methods
        $this->reviewRepository->shouldReceive('getAverageRatingForReviewable')
            ->once()->with(SubscriptionPlan::class, 10)->andReturn(5.0);
        $this->reviewRepository->shouldReceive('getTotalReviewCountForReviewable')
            ->once()->with(SubscriptionPlan::class, 10)->andReturn(2);
        $this->reviewRepository->shouldReceive('getRatingBreakdownForReviewable')
            ->once()->with(SubscriptionPlan::class, 10)
            ->andReturn([5 => 2, 4 => 0, 3 => 0, 2 => 0, 1 => 0]);

        $productSummary = $this->service->getReviewSummary(1);
        $planSummary = $this->service->getPlanReviewSummary(10);

        $this->assertEquals(3.0, $productSummary->averageRating);
        $this->assertEquals(5.0, $planSummary->averageRating);
    }

}