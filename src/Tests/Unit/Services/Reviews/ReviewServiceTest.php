<?php

namespace App\Tests\Unit\Services\Reviews;

use App\DTO\Reviews\ReviewResult;
use App\DTO\Reviews\ReviewSummaryDTO;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Models\Review;
use App\Services\Reviews\ReviewCommandService;
use App\Services\Reviews\ReviewQueryService;
use App\Services\Reviews\ReviewService;
use App\Services\Reviews\ReviewVoteService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ReviewServiceTest extends FunctionalTestCase
{
    protected ReviewCommandService $commandService;
    protected ReviewQueryService $queryService;
    protected ReviewVoteService $voteService;
    protected MemberAuthWrapper $authService;
    protected ReviewService $reviewService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandService = Mockery::mock(ReviewCommandService::class);
        $this->queryService = Mockery::mock(ReviewQueryService::class);
        $this->voteService = Mockery::mock(ReviewVoteService::class);
        $this->authService = Mockery::mock(MemberAuthWrapper::class);

        $this->reviewService = new ReviewService(
            $this->commandService,
            $this->queryService,
            $this->voteService,
            $this->authService
        );

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        //Mockery::close();
        $_SESSION = [];
        parent::tearDown();
    }

    /**
     * Helper to mock the authentication state for a test.
     * @param int|null $userId
     */
    private function mockAuthUserId(?int $userId): void
    {
        // Use shouldReceive on the AuthService mock
        $this->authService->shouldReceive('memberId')->andReturn($userId);
    }

    /**
     * Helper to mock a Model object with properties.
     * Fixes the 'stdClass returned' TypeErrors.
     * @param string $class
     * @param array $properties
     * @return object
     */
    private function mockModel(string $class, array $properties): object
    {
        $mock = Mockery::mock($class)->makePartial();
        foreach ($properties as $key => $value) {
            $mock->{$key} = $value;
        }
        return $mock;
    }

    public function testGetProductReviewsSuccess()
    {
        $productId = 1;
        $page = 1;
        $perPage = 10;

        $reviewsData = [
            'reviews' => [
                ['id' => 1, 'rating' => 5, 'title' => 'Great!']
            ],
            'pagination' => ['total' => 1]
        ];

        $summaryData = [
            'average_rating' => 4.8,
            'total_reviews' => 50,
            'rating_breakdown' => [5 => 40],
            'rating_percentages' => [5 => 80.0]
        ];

        $this->queryService->shouldReceive('getPaginatedProductReviews')
            ->once()
            ->with($productId, $page, $perPage)
            ->andReturn($reviewsData);

        $summary = Mockery::mock(\App\DTO\Reviews\ReviewSummaryDTO::class);
        $summary->shouldReceive('toArray')->andReturn($summaryData);

        $this->queryService->shouldReceive('getReviewSummary')
            ->once()
            ->with($productId)
            ->andReturn($summary);

        $result = $this->reviewService->getProductReviews($productId, $page, $perPage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertEquals(4.8, $result['average_rating']);
    }

    // --- Test createReview ---

    public function testCreateReviewSuccess()
    {
        $userId = 123;
        $productId = 45;
        $reviewData = ['rating' => 5, 'title' => 'Fantastic!', 'comment' => 'Highly recommend.'];

        $this->mockAuthUserId($userId);

        $result = ReviewResult::success('Review submitted successfully', Mockery::mock(Review::class));

        $this->commandService->shouldReceive('createReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->createReview($productId, $reviewData, $this->siteId);

        $this->assertTrue($response->success);
        $this->assertEquals('Review submitted successfully', $response->message);
    }

    public function testCreateReviewNotLoggedIn()
    {
        $this->mockAuthUserId(null); // Not logged in
        $productId = 45;
        $reviewData = ['rating' => 5, 'title' => 'Test', 'comment' => 'Test'];

        $this->expectExceptionMessage('You must be logged in to submit a review');

        $result = $this->reviewService->createReview($productId, $reviewData);

    }

    public function testCreateReviewProductNotFound()
    {
        $this->mockAuthUserId(123);
        $productId = 45;
        $reviewData = ['rating' => 5];

        $result = ReviewResult::failure('Product not found');

        $this->commandService->shouldReceive('createReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->createReview($productId, $reviewData, 1);

        $this->assertFalse($response->success);
        $this->assertEquals('Product not found', $response->message);
    }

    public function testCreateReviewAlreadyReviewed()
    {
        $userId = 123;
        $productId = 45;
        $reviewData = ['rating' => 5];

        $this->mockAuthUserId($userId);

        $result = ReviewResult::failure('You have already reviewed this product');

        $this->commandService->shouldReceive('createReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->createReview($productId, $reviewData, 1);

        $this->assertFalse($response->success);
        $this->assertEquals('You have already reviewed this product', $response->message);
    }

    public function testCreateReviewInvalidRating()
    {
        $userId = 123;
        $productId = 45;

        $this->mockAuthUserId($userId);

        $result = ReviewResult::failure('Rating must be between 1 and 5');

        $this->commandService->shouldReceive('createReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->createReview($productId, ['rating' => 6], 1);

        $this->assertFalse($response->success);
        $this->assertEquals('Rating must be between 1 and 5', $response->message);
    }

    // --- Test updateReview ---

    public function testUpdateReviewSuccess()
    {
        $userId = 123;
        $reviewId = 99;
        $updateData = ['rating' => 4, 'comment' => 'Still great.'];

        $this->mockAuthUserId($userId);

        $result = ReviewResult::success('Review updated successfully');

        $this->commandService->shouldReceive('updateReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->updateReview($reviewId, $updateData);

        $this->assertTrue($response['success']);
        $this->assertEquals('Review updated successfully', $response['message']);
    }

    public function testUpdateReviewNotLoggedIn()
    {
        $this->mockAuthUserId(null);
        $result = $this->reviewService->updateReview(99, ['rating' => 4]);

        $this->assertFalse($result['success']);
        $this->assertEquals('You must be logged in', $result['message']);
    }

    public function testUpdateReviewNotFound()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;

        $result = ReviewResult::failure('Review not found');

        $this->commandService->shouldReceive('updateReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->updateReview($reviewId, ['rating' => 4]);

        $this->assertFalse($response['success']);
        $this->assertEquals('Review not found', $response['message']);
    }

    public function testUpdateReviewNotAuthor()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;

        $result = ReviewResult::failure('You can only edit your own reviews');

        $this->commandService->shouldReceive('updateReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->updateReview($reviewId, ['rating' => 4]);

        $this->assertFalse($response['success']);
        $this->assertEquals('You can only edit your own reviews', $response['message']);
    }

    public function testUpdateReviewInvalidRating()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;

        $result = ReviewResult::failure('Rating must be between 1 and 5');

        $this->commandService->shouldReceive('updateReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->updateReview($reviewId, ['rating' => 7]);

        $this->assertFalse($response['success']);
        $this->assertEquals('Rating must be between 1 and 5', $response['message']);
    }

    // --- Test deleteReview ---

    public function testDeleteReviewSuccess()
    {
        $userId = 123;
        $reviewId = 99;

        $this->mockAuthUserId($userId);

        $result = ReviewResult::success('Review deleted successfully');

        $this->commandService->shouldReceive('deleteReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->deleteReview($reviewId);

        $this->assertTrue($response['success']);
        $this->assertEquals('Review deleted successfully', $response['message']);
    }

    public function testDeleteReviewNotAuthor()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;

        $result = ReviewResult::failure('You can only delete your own reviews');

        $this->commandService->shouldReceive('deleteReview')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->deleteReview($reviewId);

        $this->assertFalse($response['success']);
        $this->assertEquals('You can only delete your own reviews', $response['message']);
    }

    // --- Test markReviewHelpful ---

    public function testMarkReviewHelpfulNewVote()
    {
        $userId = 123;
        $reviewId = 100;

        $this->mockAuthUserId($userId);

        $result = ReviewResult::success('Thank you for your feedback', null, [
            'helpful_count' => 6,
            'unhelpful_count' => 1
        ]);

        $this->voteService->shouldReceive('markReviewHelpful')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->markReviewHelpful($reviewId, true, $this->siteId);

        $this->assertTrue($response['success']);
        $this->assertEquals(6, $response['helpful_count']);
    }

    public function testMarkReviewHelpfulRemoveSameVote()
    {
        $userId = 123;
        $reviewId = 100;

        $this->mockAuthUserId($userId);

        $result = ReviewResult::success('Vote removed', null, [
            'helpful_count' => 4,
            'unhelpful_count' => 1
        ]);

        $this->voteService->shouldReceive('markReviewHelpful')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->markReviewHelpful($reviewId, true, $this->siteId);

        $this->assertTrue($response['success']);
        $this->assertEquals('Vote removed', $response['message']);
    }

    public function testMarkReviewHelpfulChangeVote()
    {
        $userId = 123;
        $reviewId = 100;

        $this->mockAuthUserId($userId);

        $result = ReviewResult::success('Vote updated', null, [
            'helpful_count' => 6,
            'unhelpful_count' => 2
        ]);

        $this->voteService->shouldReceive('markReviewHelpful')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->markReviewHelpful($reviewId, true, $this->siteId);

        $this->assertTrue($response['success']);
        $this->assertEquals('Vote updated', $response['message']);
    }


    public function testMarkReviewHelpfulReviewNotFound()
    {
        $this->mockAuthUserId(123);
        $reviewId = 999;

        $result = ReviewResult::failure('Review not found');

        $this->voteService->shouldReceive('markReviewHelpful')
            ->once()
            ->andReturn($result);

        $response = $this->reviewService->markReviewHelpful($reviewId, true, $this->siteId);

        $this->assertFalse($response['success']);
        $this->assertEquals('Review not found', $response['message']);
    }

    // --- Test getReviewStatistics ---

    public function testGetReviewStatisticsSuccess()
    {
        $productId = 1;
        $summaryData = [
            'average_rating' => 4.0,
            'total_reviews' => 100,
            'rating_breakdown' => [5 => 50, 4 => 30, 3 => 10, 2 => 5, 1 => 5],
            'rating_percentages' => [5 => 50.0, 4 => 30.0, 3 => 10.0, 2 => 5.0, 1 => 5.0]
        ];

        $summary = Mockery::mock(ReviewSummaryDTO::class);
        $summary->shouldReceive('toArray')->andReturn($summaryData);

        $this->queryService->shouldReceive('getReviewSummary')
            ->once()
            ->with($productId)
            ->andReturn($summary);

        $result = $this->reviewService->getReviewStatistics($productId);

        $this->assertEquals(4.0, $result['average_rating']);
        $this->assertEquals(100, $result['total_reviews']);
    }

    public function testGetReviewStatisticsNoReviews()
    {
        $productId = 2;
        $summaryData = [
            'average_rating' => 0.0,
            'total_reviews' => 0,
            'rating_breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
            'rating_percentages' => [5 => 0.0, 4 => 0.0, 3 => 0.0, 2 => 0.0, 1 => 0.0]
        ];

        $summary = Mockery::mock(ReviewSummaryDTO::class);
        $summary->shouldReceive('toArray')->andReturn($summaryData);

        $this->queryService->shouldReceive('getReviewSummary')
            ->once()
            ->with($productId)
            ->andReturn($summary);

        $result = $this->reviewService->getReviewStatistics($productId);

        $this->assertEquals(0, $result['total_reviews']);
        $this->assertEquals(0.0, $result['average_rating']);
    }

    // --- Test canUserReview ---

    public function testCanUserReviewSuccess()
    {
        $userId = 123;
        $productId = 10;

        $this->mockAuthUserId($userId);

        $this->queryService->shouldReceive('canUserReview')
            ->once()
            ->with($productId, $userId)
            ->andReturn([
                'can_review' => true,
                'reason' => null
            ]);

        $result = $this->reviewService->canUserReview($productId);

        $this->assertTrue($result['can_review']);
        $this->assertNull($result['reason']);
    }

    public function testCanUserReviewNotLoggedIn()
    {
        $this->mockAuthUserId(null);
        $productId = 10;

        $this->queryService->shouldReceive('canUserReview')
            ->once()
            ->with($productId, null)
            ->andReturn([
                'can_review' => false,
                'reason' => 'You must be logged in to submit a review'
            ]);

        $result = $this->reviewService->canUserReview($productId);

        $this->assertFalse($result['can_review']);
        $this->assertEquals('You must be logged in to submit a review', $result['reason']);
    }

    public function testCanUserReviewAlreadyReviewed()
    {
        $userId = 123;
        $productId = 10;

        $this->mockAuthUserId($userId);

        $this->queryService->shouldReceive('canUserReview')
            ->once()
            ->with($productId, $userId)
            ->andReturn([
                'can_review' => false,
                'reason' => 'You have already reviewed this product'
            ]);

        $result = $this->reviewService->canUserReview($productId);

        $this->assertFalse($result['can_review']);
        $this->assertEquals('You have already reviewed this product', $result['reason']);
    }

    // --- Test protected getSessionId (Side Effect) ---

    public function testGetSessionIdCreatesAndReturnsSession()
    {
        $method = new \ReflectionMethod(ReviewService::class, 'getSessionId');
        $method->setAccessible(true);

        $sessionId1 = $method->invoke($this->reviewService);
        $this->assertStringStartsWith('review_', $sessionId1);
        $this->assertArrayHasKey('review_session_id', $_SESSION);

        $sessionId2 = $method->invoke($this->reviewService);
        $this->assertEquals($sessionId1, $sessionId2);
    }
}