<?php

namespace App\Tests\Unit\Services;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Support\Collection;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewHelpful;
use App\Repositories\ProductRepository;
use App\Repositories\ReviewHelpfulRepository;
use App\Repositories\ReviewRepository;
use App\Services\ReviewService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReviewServiceTest extends TestCase
{
    protected ReviewRepository $reviewRepository;
    protected ReviewHelpfulRepository $reviewHelpfulRepository;
    protected ProductRepository $productRepository;
    protected ReviewService $reviewService;
    protected AuthenticationService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock all dependencies
        $this->reviewRepository = Mockery::mock(ReviewRepository::class);
        $this->reviewHelpfulRepository = Mockery::mock(ReviewHelpfulRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->authService = Mockery::mock(AuthenticationService::class); // <-- NEW

        // Instantiate the service with mocked dependencies
        $this->reviewService = new ReviewService(
            $this->reviewRepository,
            $this->reviewHelpfulRepository,
            $this->productRepository,
            $this->authService // <-- NEW INJECTION
        );

        // Reset session for each test (for getSessionId)
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();
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
        $this->authService->shouldReceive('getUserId')->andReturn($userId);
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

    // Assuming the following placeholder class exists in your test file setup:
// use Illuminate\Support\Collection;

    public function testGetProductReviewsSuccess()
    {
        $productId = 1;
        $page = 1;
        $perPage = 10;

        $expectedReviewData = [
            'id' => 101, 'rating' => 5, 'title' => 'Great!', 'comment' => 'Loved it.',
            'author_name' => 'John Doe', 'is_verified_purchase' => true,
            'helpful_count' => 5, 'unhelpful_count' => 1, 'formatted_date' => 'Yesterday',
            'created_at' => '2023-01-01 10:00:00'
        ];
        $expectedReviews = [$expectedReviewData];

        $mockDataCollection = Mockery::mock(Collection::class);

        $mockMappedCollection = Mockery::mock(Collection::class);
        $mockMappedCollection->shouldReceive('toArray')
            ->once()
            ->andReturn($expectedReviews);

        $mockDataCollection->shouldReceive('map')
            ->once()
            ->andReturn($mockMappedCollection);

        $mockReviewData = [
            'data' => $mockDataCollection, // <-- Mocked collection
            'pagination' => ['total' => 1, 'current_page' => 1],
        ];

        $this->reviewRepository->shouldReceive('findByProduct')
            ->once()
            ->with($productId, $page, $perPage)
            ->andReturn($mockReviewData);

        $this->reviewRepository->shouldReceive('getAverageRating')
            ->once()->with($productId)->andReturn(4.8);

        $this->reviewRepository->shouldReceive('getTotalReviewCount')
            ->once()->with($productId)->andReturn(50);

        $this->reviewRepository->shouldReceive('getRatingBreakdown')
            ->once()->with($productId)->andReturn([5 => 40, 4 => 5, 3 => 3, 2 => 1, 1 => 1]);

        $result = $this->reviewService->getProductReviews($productId, $page, $perPage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(1, $result['reviews']);
        $this->assertEquals(4.8, $result['average_rating']);
        $this->assertEquals(50, $result['total_reviews']);
        $this->assertEquals(101, $result['reviews'][0]['id']);
    }

    // --- Test createReview ---

    public function testCreateReviewSuccess()
    {
        $userId = 123;
        $productId = 45;
        $reviewData = ['rating' => 5, 'title' => 'Fantastic!', 'comment' => 'Highly recommend.'];

        // Mock models to satisfy type hints
        $mockProduct = $this->mockModel(Product::class, ['id' => $productId, 'site_id' => 1]);
        $mockReview = $this->mockModel(Review::class, ['id' => 500, 'rating' => 5, 'product_id' => $productId]);

        $this->mockAuthUserId($userId); // Logged in

        $this->productRepository->shouldReceive('find')->once()->with($productId)->andReturn($mockProduct); // FIX: Mocked model
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->with($productId, $userId)->andReturn(false);
        $this->reviewRepository->shouldReceive('create')->once()->andReturn($mockReview);

        $result = $this->reviewService->createReview($productId, $reviewData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Review submitted successfully', $result['message']);
    }

    public function testCreateReviewNotLoggedIn()
    {
        $this->mockAuthUserId(null); // Not logged in
        $productId = 45;
        $reviewData = ['rating' => 5, 'title' => 'Test', 'comment' => 'Test'];

        $result = $this->reviewService->createReview($productId, $reviewData);

        $this->assertFalse($result['success']);
        $this->assertEquals('You must be logged in to submit a review', $result['message']);
        $this->productRepository->shouldNotHaveReceived('find');
    }

    public function testCreateReviewProductNotFound()
    {
        $this->mockAuthUserId(123);
        $productId = 45;
        $reviewData = ['rating' => 5];

        $this->productRepository->shouldReceive('find')->once()->with($productId)->andReturn(null);

        $result = $this->reviewService->createReview($productId, $reviewData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Product not found', $result['message']); // <-- NOW PASSES
    }

    public function testCreateReviewAlreadyReviewed()
    {
        $userId = 123;
        $productId = 45;
        $reviewData = ['rating' => 5];
        $mockProduct = $this->mockModel(Product::class, ['id' => $productId, 'site_id' => 1]); // FIX: Mocked model

        $this->mockAuthUserId($userId);
        $this->productRepository->shouldReceive('find')->once()->andReturn($mockProduct); // FIX: Mocked model
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->with($productId, $userId)->andReturn(true);

        $result = $this->reviewService->createReview($productId, $reviewData);

        $this->assertFalse($result['success']);
        $this->assertEquals('You have already reviewed this product', $result['message']);
    }

    public function testCreateReviewInvalidRating()
    {
        $userId = 123;
        $productId = 45;
        $mockProduct = $this->mockModel(Product::class, ['id' => $productId, 'site_id' => 1]); // FIX: Mocked model

        $this->mockAuthUserId($userId);
        $this->productRepository->shouldReceive('find')->once()->andReturn($mockProduct); // FIX: Mocked model
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->andReturn(false);

        $result = $this->reviewService->createReview($productId, ['rating' => 6]);
        $this->assertFalse($result['success']);
        $this->assertEquals('Rating must be between 1 and 5', $result['message']);
    }

    // --- Test updateReview ---

    public function testUpdateReviewSuccess()
    {
        $userId = 123;
        $reviewId = 99;
        $updateData = ['rating' => 4, 'comment' => 'Still great.'];

        // Mock Review model to satisfy type hints
        $mockReview = $this->mockModel(Review::class, ['id' => $reviewId, 'user_id' => $userId]);

        $this->mockAuthUserId($userId);

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview); // FIX: Mocked model
        $this->reviewRepository->shouldReceive('update')->once()->with($reviewId, Mockery::subset([
            'rating' => 4, 'comment' => $updateData['comment']
        ]))->andReturn($mockReview);

        $result = $this->reviewService->updateReview($reviewId, $updateData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Review updated successfully', $result['message']);
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

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn(null);

        $result = $this->reviewService->updateReview($reviewId, ['rating' => 4]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Review not found', $result['message']);
    }

    public function testUpdateReviewNotAuthor()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;
        $mockReview = $this->mockModel(Review::class, ['id' => $reviewId, 'user_id' => 456]); // FIX: Mocked model

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview);

        $result = $this->reviewService->updateReview($reviewId, ['rating' => 4]);

        $this->assertFalse($result['success']);
        $this->assertEquals('You can only edit your own reviews', $result['message']);
    }

    public function testUpdateReviewInvalidRating()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;
        $mockReview = $this->mockModel(Review::class, ['id' => $reviewId, 'user_id' => 123]); // FIX: Mocked model

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview);

        $result = $this->reviewService->updateReview($reviewId, ['rating' => 7]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Rating must be between 1 and 5', $result['message']);
        $this->reviewRepository->shouldNotHaveReceived('update');
    }

    // --- Test deleteReview ---

    public function testDeleteReviewSuccess()
    {
        $userId = 123;
        $reviewId = 99;
        $mockReview = $this->mockModel(Review::class, ['id' => $reviewId, 'user_id' => $userId]); // FIX: Mocked model

        $this->mockAuthUserId($userId);

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview);
        $this->reviewRepository->shouldReceive('delete')->once()->with($reviewId)->andReturn(true);

        $result = $this->reviewService->deleteReview($reviewId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Review deleted successfully', $result['message']);
    }

    public function testDeleteReviewNotAuthor()
    {
        $this->mockAuthUserId(123);
        $reviewId = 99;
        $mockReview = $this->mockModel(Review::class, ['id' => $reviewId, 'user_id' => 456]); // FIX: Mocked model

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview);
        $this->reviewRepository->shouldNotReceive('delete');

        $result = $this->reviewService->deleteReview($reviewId);

        $this->assertFalse($result['success']);
        $this->assertEquals('You can only delete your own reviews', $result['message']);
    }

    // --- Test markReviewHelpful ---

    public function testMarkReviewHelpfulNewVote()
    {
        $userId = 123;
        $reviewId = 100;
        $isHelpful = true;

        // Use Mockery::mock(Review::class)->makePartial() for methods like update()
        $mockReview = Mockery::mock(Review::class)->makePartial();
        $mockReview->id = $reviewId;
        $mockReview->helpful_count = 5;
        $mockReview->unhelpful_count = 1;
        $mockReview->site_id = 1;

        $updatedReview = $this->mockModel(Review::class, ['id' => $reviewId, 'helpful_count' => 6, 'unhelpful_count' => 1]);

        $this->mockAuthUserId($userId);

        $this->reviewHelpfulRepository->shouldReceive('getUserVote')->once()->andReturn(null);
        $this->reviewHelpfulRepository->shouldReceive('create')->once();

        $this->reviewRepository->shouldReceive('find')->twice()->with($reviewId)->andReturn($mockReview, $updatedReview);
        $this->reviewRepository->shouldReceive('incrementHelpfulCount')->once()->with($reviewId);
        $this->reviewRepository->shouldNotReceive('incrementUnhelpfulCount');

        $result = $this->reviewService->markReviewHelpful($reviewId, $isHelpful);

        $this->assertTrue($result['success']);
        $this->assertEquals(6, $result['helpful_count']);
    }

    public function testMarkReviewHelpfulRemoveSameVote()
    {
        $userId = 123;
        $reviewId = 100;
        $isHelpful = true;
        $initialHelpfulCount = 5;

        $mockReview = Mockery::mock(Review::class)->makePartial();
        $mockReview->id = $reviewId;
        $mockReview->helpful_count = $initialHelpfulCount;
        $mockReview->unhelpful_count = 1;

        // FIX APPLIED: Mock the ReviewHelpful model instead of using stdClass
        $existingVote = $this->mockModel(ReviewHelpful::class, ['id' => 1, 'is_helpful' => true]);

        $this->mockAuthUserId($userId);

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview);
        // Ensure getUserVote returns the mocked model
        $this->reviewHelpfulRepository->shouldReceive('getUserVote')->once()->andReturn($existingVote);

        // Expect the model update to be called
        $mockReview->shouldReceive('update')->once()->with(['helpful_count' => $initialHelpfulCount - 1]);
        $this->reviewHelpfulRepository->shouldReceive('delete')->once()->with($existingVote->id);

        $result = $this->reviewService->markReviewHelpful($reviewId, $isHelpful);

        $this->assertTrue($result['success']);
        $this->assertEquals('Vote removed', $result['message']);
    }

    public function testMarkReviewHelpfulChangeVote()
    {
        $userId = 123;
        $reviewId = 100;
        $isHelpful = true; // Change from 'unhelpful' to 'helpful'

        $mockReview = Mockery::mock(Review::class)->makePartial();
        $mockReview->id = $reviewId;
        $mockReview->helpful_count = 5;
        $mockReview->unhelpful_count = 3;

        // FIX APPLIED: Mock the ReviewHelpful model instead of using stdClass
        $existingVote = $this->mockModel(ReviewHelpful::class, ['id' => 1, 'is_helpful' => false]);

        $this->mockAuthUserId($userId);

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn($mockReview);
        // Ensure getUserVote returns the mocked model
        $this->reviewHelpfulRepository->shouldReceive('getUserVote')->once()->andReturn($existingVote);

        // Expect the model update to be called
        $mockReview->shouldReceive('update')->once()->with([
            'helpful_count' => 6,
            'unhelpful_count' => 2
        ]);
        $this->reviewHelpfulRepository->shouldReceive('update')->once()->with($existingVote->id, ['is_helpful' => true]);

        $result = $this->reviewService->markReviewHelpful($reviewId, $isHelpful);

        $this->assertTrue($result['success']);
        $this->assertEquals('Vote updated', $result['message']);
    }


    public function testMarkReviewHelpfulReviewNotFound()
    {
        $this->mockAuthUserId(123);
        $reviewId = 999;
        $isHelpful = true;

        $this->reviewRepository->shouldReceive('find')->once()->with($reviewId)->andReturn(null);

        $result = $this->reviewService->markReviewHelpful($reviewId, $isHelpful);

        $this->assertFalse($result['success']);
        $this->assertEquals('Review not found', $result['message']);
    }

    // --- Test getReviewStatistics ---

    public function testGetReviewStatisticsSuccess()
    {
        $productId = 1;
        $averageRating = 4.0;
        $totalReviews = 100;
        $breakdown = [5 => 50, 4 => 30, 3 => 10, 2 => 5, 1 => 5];
        $expectedPercentages = [5 => 50.0, 4 => 30.0, 3 => 10.0, 2 => 5.0, 1 => 5.0];

        $this->reviewRepository->shouldReceive('getAverageRating')->once()->with($productId)->andReturn($averageRating);
        $this->reviewRepository->shouldReceive('getTotalReviewCount')->once()->with($productId)->andReturn($totalReviews);
        $this->reviewRepository->shouldReceive('getRatingBreakdown')->once()->with($productId)->andReturn($breakdown);

        $result = $this->reviewService->getReviewStatistics($productId);

        $this->assertEquals($averageRating, $result['average_rating']);
        $this->assertEquals($totalReviews, $result['total_reviews']);
        $this->assertEquals($breakdown, $result['rating_breakdown']);
        $this->assertEquals($expectedPercentages, $result['rating_percentages']);
    }

    public function testGetReviewStatisticsNoReviews()
    {
        $productId = 2;
        $averageRating = 0.0; // FIX: Should return float to satisfy type hint
        $totalReviews = 0;
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        $this->reviewRepository->shouldReceive('getAverageRating')->once()->andReturn($averageRating); // FIX: Return 0.0
        $this->reviewRepository->shouldReceive('getTotalReviewCount')->once()->andReturn($totalReviews);
        $this->reviewRepository->shouldReceive('getRatingBreakdown')->once()->andReturn($breakdown);

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

        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->with($productId, $userId)->andReturn(false);

        $result = $this->reviewService->canUserReview($productId);

        $this->assertTrue($result['can_review']);
        $this->assertNull($result['reason']);
    }

    public function testCanUserReviewNotLoggedIn()
    {
        $this->mockAuthUserId(null);
        $productId = 10;

        $result = $this->reviewService->canUserReview($productId);

        $this->assertFalse($result['can_review']);
        $this->assertEquals('You must be logged in to submit a review', $result['reason']);
        $this->reviewRepository->shouldNotHaveReceived('hasUserReviewedProduct');
    }

    public function testCanUserReviewAlreadyReviewed()
    {
        $userId = 123;
        $productId = 10;

        $this->mockAuthUserId($userId);

        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->with($productId, $userId)->andReturn(true);

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

    // --- Test protected isVerifiedPurchase (Side Effect/Implementation detail) ---

    public function testIsVerifiedPurchaseReturnsFalse()
    {
        $method = new \ReflectionMethod(ReviewService::class, 'isVerifiedPurchase');
        $method->setAccessible(true);

        $result = $method->invoke($this->reviewService, 123, 45);

        // Current implementation is hardcoded to false
        $this->assertFalse($result);
    }
}