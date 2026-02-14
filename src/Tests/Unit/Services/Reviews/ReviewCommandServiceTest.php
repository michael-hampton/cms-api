<?php

namespace App\Tests\Unit\Services\Reviews;

use App\DTO\Reviews\CreateReviewDTO;
use App\DTO\Reviews\UpdateReviewDTO;
use App\Framework\Database\Database;
use App\Models\Product;
use App\Models\Review;
use App\Repositories\Product\ProductRepository;
use App\Repositories\ReviewRepository;
use App\Services\Reviews\ReviewCommandService;
use App\Services\Reviews\ReviewPolicy;
use App\Services\Reviews\VerifiedPurchaseResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReviewCommandServiceTest extends TestCase
{
    private $database;
    private $reviewRepository;
    private $productRepository;
    private $reviewPolicy;
    private $verifiedPurchaseResolver;
    private ReviewCommandService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Mockery::mock(Database::class);
        $this->reviewRepository = Mockery::mock(ReviewRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->reviewPolicy = Mockery::mock(ReviewPolicy::class);
        $this->verifiedPurchaseResolver = Mockery::mock(VerifiedPurchaseResolver::class);

        $this->service = new ReviewCommandService(
            $this->database,
            $this->reviewRepository,
            $this->productRepository,
            $this->reviewPolicy,
            $this->verifiedPurchaseResolver
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateReviewSuccess()
    {
        $dto = new CreateReviewDTO(
            productId: 1,
            userId: 123,
            rating: 5,
            title: 'Great!',
            comment: 'Loved it',
            siteId: 1
        );

        $product = Mockery::mock(Product::class)->makePartial();
        $product->site_id = 1;

        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;

        $this->reviewPolicy->shouldReceive('canCreate')
            ->once()
            ->with(123, 1)
            ->andReturn(true);

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')
            ->once()
            ->with(1, 123)
            ->andReturn(false);

        $this->verifiedPurchaseResolver->shouldReceive('isVerified')
            ->once()
            ->with(123, 1)
            ->andReturn(false);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($review) {
                return $callback();
            });

        $this->reviewRepository->shouldReceive('create')
            ->once()
            ->andReturn($review);

        $result = $this->service->createReview($dto, 123);

        $this->assertTrue($result->success);
        $this->assertEquals('Review submitted successfully', $result->message);
        $this->assertNotNull($result->review);
    }

    public function testCreateReviewFailsWhenUserCannotCreate()
    {
        $dto = new CreateReviewDTO(
            productId: 1,
            userId: 123,
            rating: 5,
            title: 'Great!',
            comment: 'Loved it',
            siteId: 1
        );

        $this->reviewPolicy->shouldReceive('canCreate')
            ->once()
            ->with(123, 1)
            ->andReturn(false);

        $result = $this->service->createReview($dto, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('You must be logged in to submit a review', $result->message);
    }

    public function testCreateReviewFailsWhenProductNotFound()
    {
        $dto = new CreateReviewDTO(
            productId: 999,
            userId: 123,
            rating: 5,
            title: 'Great!',
            comment: 'Loved it',
            siteId: 1
        );

        $this->reviewPolicy->shouldReceive('canCreate')
            ->once()
            ->andReturn(true);

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->createReview($dto, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('Product not found', $result->message);
    }

    public function testCreateReviewFailsWhenAlreadyReviewed()
    {
        $dto = new CreateReviewDTO(
            productId: 1,
            userId: 123,
            rating: 5,
            title: 'Great!',
            comment: 'Loved it',
            siteId: 1
        );

        $product = Mockery::mock(Product::class)->makePartial();

        $this->reviewPolicy->shouldReceive('canCreate')->once()->andReturn(true);
        $this->productRepository->shouldReceive('find')->once()->andReturn($product);
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')
            ->once()
            ->with(1, 123)
            ->andReturn(true);

        $result = $this->service->createReview($dto, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('You have already reviewed this product', $result->message);
    }

    public function testCreateReviewFailsWhenRatingInvalid()
    {
        $dto = new CreateReviewDTO(
            productId: 1,
            userId: 123,
            rating: 6,
            title: 'Great!',
            comment: 'Loved it',
            siteId: 1
        );

        $product = Mockery::mock(Product::class)->makePartial();

        $this->reviewPolicy->shouldReceive('canCreate')->once()->andReturn(true);
        $this->productRepository->shouldReceive('find')->once()->andReturn($product);
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->andReturn(false);

        $result = $this->service->createReview($dto, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('Rating must be between 1 and 5', $result->message);
    }

    public function testCreateReviewWithVerifiedPurchase()
    {
        $dto = new CreateReviewDTO(
            productId: 1,
            userId: 123,
            rating: 5,
            title: 'Great!',
            comment: 'Loved it',
            siteId: 1
        );

        $product = Mockery::mock(Product::class)->makePartial();
        $product->site_id = 1;
        $review = Mockery::mock(Review::class)->makePartial();

        $this->reviewPolicy->shouldReceive('canCreate')->once()->andReturn(true);
        $this->productRepository->shouldReceive('find')->once()->andReturn($product);
        $this->reviewRepository->shouldReceive('hasUserReviewedProduct')->once()->andReturn(false);
        $this->verifiedPurchaseResolver->shouldReceive('isVerified')
            ->once()
            ->with(123, 1)
            ->andReturn(true);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) {
            return $callback();
        });

        $this->reviewRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['is_verified_purchase'] === true;
            }))
            ->andReturn($review);

        $result = $this->service->createReview($dto, 123);

        $this->assertTrue($result->success);
    }

    public function testUpdateReviewSuccess()
    {
        $dto = new UpdateReviewDTO(rating: 4, comment: 'Updated');

        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;
        $review->user_id = 123;

        $this->reviewRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($review);

        $this->reviewPolicy->shouldReceive('canEdit')
            ->once()
            ->with($review, 123)
            ->andReturn(true);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->reviewRepository->shouldReceive('update')
            ->once()
            ->with(1, ['rating' => 4, 'comment' => 'Updated'])
            ->andReturn($review);

        $result = $this->service->updateReview(1, $dto, 123);

        $this->assertTrue($result->success);
        $this->assertEquals('Review updated successfully', $result->message);
    }

    public function testUpdateReviewFailsWhenNotFound()
    {
        $dto = new UpdateReviewDTO(rating: 4);

        $this->reviewRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->updateReview(999, $dto, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('Review not found', $result->message);
    }

    public function testUpdateReviewFailsWhenNotOwner()
    {
        $dto = new UpdateReviewDTO(rating: 4);

        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $this->reviewRepository->shouldReceive('find')->once()->andReturn($review);
        $this->reviewPolicy->shouldReceive('canEdit')
            ->once()
            ->with($review, 456)
            ->andReturn(false);

        $result = $this->service->updateReview(1, $dto, 456);

        $this->assertFalse($result->success);
        $this->assertEquals('You can only edit your own reviews', $result->message);
    }

    public function testUpdateReviewFailsWithInvalidRating()
    {
        $dto = new UpdateReviewDTO(rating: 7);

        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $this->reviewRepository->shouldReceive('find')->once()->andReturn($review);
        $this->reviewPolicy->shouldReceive('canEdit')->once()->andReturn(true);

        $result = $this->service->updateReview(1, $dto, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('Rating must be between 1 and 5', $result->message);
    }

    public function testUpdateReviewWithPartialData()
    {
        $dto = new UpdateReviewDTO(comment: 'Updated comment only');

        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $this->reviewRepository->shouldReceive('find')->once()->andReturn($review);
        $this->reviewPolicy->shouldReceive('canEdit')->once()->andReturn(true);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($c) {
            return $c();
        });
        $this->reviewRepository->shouldReceive('update')
            ->once()
            ->with(1, ['comment' => 'Updated comment only'])
            ->andReturn($review);

        $result = $this->service->updateReview(1, $dto, 123);

        $this->assertTrue($result->success);
    }

    public function testDeleteReviewSuccess()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;
        $review->user_id = 123;

        $this->reviewRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($review);

        $this->reviewPolicy->shouldReceive('canDelete')
            ->once()
            ->with($review, 123)
            ->andReturn(true);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->reviewRepository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteReview(1, 123);

        $this->assertTrue($result->success);
        $this->assertEquals('Review deleted successfully', $result->message);
    }

    public function testDeleteReviewFailsWhenNotFound()
    {
        $this->reviewRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->deleteReview(999, 123);

        $this->assertFalse($result->success);
        $this->assertEquals('Review not found', $result->message);
    }

    public function testDeleteReviewFailsWhenNotOwner()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $this->reviewRepository->shouldReceive('find')->once()->andReturn($review);
        $this->reviewPolicy->shouldReceive('canDelete')
            ->once()
            ->with($review, 456)
            ->andReturn(false);

        $result = $this->service->deleteReview(1, 456);

        $this->assertFalse($result->success);
        $this->assertEquals('You can only delete your own reviews', $result->message);
    }
}