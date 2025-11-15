<?php

namespace App\Tests\Functional\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Session\Session;
use App\Models\Review;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

// Assumed to exist
// Assumed to exist

// Used for authentication

class ReviewControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private int $productId;
    private int $otherProductId;
    private $member;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have a product to review (mocking the Product model creation)
        $product = $this->createProduct();
        $this->productId = $product->id;

        $otherProduct = $this->createProduct();
        $this->otherProductId = $otherProduct->id;

        $this->member = $this->createMember();

        Session::put('member_id', $this->member->id);
        Session::put('member_authenticated', true);

    }

//    protected function tearDown(): void
//    {
//        Session::flush();
//        Session::put('authenticated', false);
//        Session::put('user_id', null);;
//    }

    // --- GET /api/products/{productId}/reviews ---

    public function testIndexReturnsProductReviews()
    {
        // Arrange: Create a review for the test product
        Review::create([
            'product_id' => $this->productId,
            'user_id' => $this->member->id,
            'rating' => 5,
            'comment' => 'Great product!',
            'author_name' => 'Test User',
            'is_approved' => true,
            'site_id' => $this->siteId
        ]);
        // Create a review for another product (should not be returned)
        Review::create([
            'product_id' => $this->otherProductId,
            'user_id' => $this->member->id,
            'rating' => 1,
            'comment' => 'Bad product!',
            'author_name' => 'Test User',
            'is_approved' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $response = $this->getForSite("/api/products/{$this->productId}/reviews");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('reviews', $data);
        $this->assertCount(1, $data['reviews']);
        $this->assertEquals('Great product!', $data['reviews'][0]['comment']);
        $this->assertEquals(5.0, $data['average_rating']);
        $this->assertEquals(1, $data['total_reviews']);
    }

    public function testIndexHandlesPagination()
    {
        // Arrange: Create 3 reviews
        for ($i = 1; $i <= 3; $i++) {
            Review::create([
                'product_id' => $this->productId,
                'user_id' => $this->member->id,
                'rating' => 4,
                'comment' => "Review {$i}",
                'author_name' => 'User',
                'is_approved' => true,
                'site_id' => $this->siteId
            ]);
        }

        // Act: Request page 2 with 2 items per page
        $response = $this->getForSite("/api/products/{$this->productId}/reviews?page=2&per_page=2");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['reviews']);
        $this->assertEquals(2, $data['pagination']['current_page']);
    }

    // --- POST /api/products/{productId}/reviews ---

    public function testStoreCreatesNewReview()
    {
        $reviewData = [
            'rating' => 4,
            'title' => 'Decent!',
            'comment' => 'A solid purchase for the price.',
        ];

        // Act
        $response = $this->postForSite("/api/products/{$this->productId}/reviews", $reviewData);

        // Assert
        // Success status code from the service is 200, as defined in the controller
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('review', $data);
        $this->assertEquals(4, $data['review']['rating']);

        // Verify it was created in the database
        $this->assertNotNull(Review::where('product_id', $this->productId)->first());
    }

    public function testStoreReturnsErrorOnValidationFailure()
    {
        $reviewData = [
            'rating' => 6, // Invalid rating
            'comment' => 'Too high',
        ];

        // Act
        $response = $this->postForSite("/api/products/{$this->productId}/reviews", $reviewData);

        // Assert
        // The ReviewService returns 400 status on failure
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Rating must be between 1 and 5', $data['message']);
    }

    // Since the service handles "Not Logged In" and "Already Reviewed" checks,
    // we'll test these scenarios.

    public function testStoreReturnsErrorIfNotLoggedIn()
    {
       $this->clearUser();

        $response = $this->postForSiteUnauthenticated("/api/products/{$this->productId}/reviews", ['rating' => 5]);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('logged in', $data['message']);
    }

    // --- PUT /api/reviews/{reviewId} ---

    public function testUpdateModifiesExistingReview()
    {
        // Arrange: Create a review by the authenticated user
        $review = Review::create([
            'product_id' => $this->productId,
            'user_id' => $this->member->id,
            'rating' => 3,
            'comment' => 'Initial comment',
            'author_name' => 'Test User',
            'is_approved' => true,
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'rating' => 5,
            'comment' => 'Updated comment: Excellent!',
        ];

        // Act
        $response = $this->putForSiteUnauthenticated("/api/reviews/{$review->id}", $updateData);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify the change in the database
        $updatedReview = Review::find($review->id);
        $this->assertEquals(5, $updatedReview->rating);
        $this->assertEquals('Updated comment: Excellent!', $updatedReview->comment);
    }

    public function testUpdateReturnsErrorIfReviewNotFound()
    {
        $nonExistentReviewId = 99999;

        $response = $this->putForSite("/api/reviews/{$nonExistentReviewId}", ['rating' => 5]);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Review not found', $data['message']);
    }

    public function testUpdateReturnsErrorIfNotAuthor()
    {
        // Arrange: Create a review by a DIFFERENT user
        $otherUser = $this->createMember();
        $review = Review::create([
            'product_id' => $this->productId,
            'user_id' => $otherUser->id,
            'rating' => 3,
            'comment' => 'Not my review',
            'author_name' => 'Other User',
            'is_approved' => true,
            'site_id' => $this->siteId
        ]);

        // Act: Authenticated user tries to update the review
        $response = $this->putForSite("/api/reviews/{$review->id}", ['rating' => 5]);

        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('You can only edit your own reviews', $data['message']);
    }

    // --- DELETE /api/reviews/{reviewId} ---

    public function testDestroyDeletesReview()
    {
        // Arrange: Create a review by the authenticated user
        $review = Review::create([
            'product_id' => $this->productId,
            'user_id' => $this->member->id,
            'rating' => 3,
            'comment' => 'To be deleted',
            'author_name' => 'Test User',
            'is_approved' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $response = $this->deleteForSiteUnauthenticated("/api/reviews/{$review->id}");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify deletion in the database
        $this->assertNull(Review::find($review->id));
    }

    public function testDestroyReturnsErrorIfNotLoggedIn()
    {
        // Arrange: Create a review (user is irrelevant since we unauthenticate)
        $review = Review::create([
            'product_id' => $this->productId,
            'user_id' => $this->member->id,
            'rating' => 3,
            'site_id' => $this->siteId
        ]);

        $this->clearUser();
        $response = $this->deleteForSiteUnauthenticated("/api/reviews/{$review->id}");

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('You must be logged in', $data['message']);
    }

    // --- POST /api/reviews/{reviewId}/helpful ---

    public function testMarkHelpfulIncrementsCount()
    {
        // Arrange: Create an unhelpful review
        $review = Review::create([
            'product_id' => $this->productId,
            'user_id' => null, // Not tied to an actual user
            'rating' => 2,
            'helpful_count' => 0,
            'unhelpful_count' => 0,
            'site_id' => $this->siteId
        ]);

        // Act: Mark as helpful (default is_helpful=true)
        $response = $this->postForSite("/api/reviews/{$review->id}/helpful");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['helpful_count']);
        $this->assertEquals(0, $data['unhelpful_count']);
    }

    public function testMarkHelpfulDecrementsOnUnhelpfulVote()
    {
        // Arrange: Create a review
        $review = Review::create([
            'product_id' => $this->productId,
            'user_id' => null,
            'rating' => 2,
            'helpful_count' => 0,
            'unhelpful_count' => 0,
            'site_id' => $this->siteId
        ]);

        // Act: Mark as NOT helpful
        $response = $this->postForSite("/api/reviews/{$review->id}/helpful", ['is_helpful' => false]);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals(0, $data['helpful_count']);
        $this->assertEquals(1, $data['unhelpful_count']);
    }

    // --- GET /api/products/{productId}/statistics ---

    public function testStatisticsReturnsReviewData()
    {
        // Arrange: Create reviews
        Review::create(['product_id' => $this->productId, 'rating' => 5, 'site_id' => $this->siteId, 'user_id' => null, 'is_approved' => true]);;
        Review::create(['product_id' => $this->productId, 'rating' => 4, 'site_id' => $this->siteId, 'user_id' => null, 'is_approved' => true]);;
        Review::create(['product_id' => $this->productId, 'rating' => 4, 'site_id' => $this->siteId, 'user_id' => null, 'is_approved' => true]);

        // Act
        $response = $this->getForSite("/api/products/{$this->productId}/reviews/statistics");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('average_rating', $data);
        $this->assertArrayHasKey('total_reviews', $data);
        $this->assertArrayHasKey('rating_breakdown', $data);

        $this->assertEquals(3, $data['total_reviews']);
        // Average: (5 + 4 + 4) / 3 = 4.333...
        $this->assertGreaterThanOrEqual(4.3, $data['average_rating']);
        $this->assertEquals(33.3, $data['rating_percentages']['5']); // 1/3
        $this->assertEquals(66.7, $data['rating_percentages']['4']); // 2/3
    }

    // --- GET /api/products/{productId}/can-review ---

    public function testCanReviewReturnsTrueForNewUser()
    {
        // Act
        $response = $this->getForSite("/api/products/{$this->productId}/reviews/can-review");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['can_review']);
        $this->assertNull($data['reason']);
    }

    public function testCanReviewReturnsFalseIfAlreadyReviewed()
    {
        // Arrange: User has already reviewed
        Review::create([
            'product_id' => $this->productId,
            'user_id' => $this->member->id,
            'rating' => 5,
            'site_id' => $this->siteId
        ]);

        // Act
        $response = $this->getForSiteUnauthenticated("/api/products/{$this->productId}/reviews/can-review");

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['can_review']);
        $this->assertEquals('You have already reviewed this product', $data['reason']);
    }

    public function testCanReviewReturnsFalseIfNotLoggedIn()
    {
        $this->clearUser();

        // Act
        $response = $this->getForSiteUnauthenticated("/api/products/{$this->productId}/reviews/can-review");

        // Assert
        $this->assertEquals(200, $response->getStatusCode()); // Status 200 is used even for false outcome
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['can_review']);
        $this->assertEquals('You must be logged in to submit a review', $data['reason']);
    }

    private function clearUser()
    {
        Session::flush();
        MemberAuth::$member = null;
    }
}