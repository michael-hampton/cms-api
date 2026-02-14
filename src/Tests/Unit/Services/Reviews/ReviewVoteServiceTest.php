<?php

namespace App\Tests\Unit\Services\Reviews;

use App\DTO\Reviews\ReviewActionContext;
use App\Framework\Database\Database;
use App\Models\Review;
use App\Models\ReviewHelpful;
use App\Repositories\ReviewHelpfulRepository;
use App\Repositories\ReviewRepository;
use App\Services\Reviews\ReviewVoteService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReviewVoteServiceTest extends TestCase
{
    private $database;
    private $reviewRepository;
    private $reviewHelpfulRepository;
    private ReviewVoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Mockery::mock(Database::class);
        $this->reviewRepository = Mockery::mock(ReviewRepository::class);
        $this->reviewHelpfulRepository = Mockery::mock(ReviewHelpfulRepository::class);

        $this->service = new ReviewVoteService(
            $this->database,
            $this->reviewRepository,
            $this->reviewHelpfulRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testMarkReviewHelpfulCreatesNewVote()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;
        $review->helpful_count = 5;
        $review->unhelpful_count = 1;
        $review->site_id = 1;

        $updatedReview = Mockery::mock(Review::class)->makePartial();
        $updatedReview->helpful_count = 6;
        $updatedReview->unhelpful_count = 1;

        $context = ReviewActionContext::fromAuth(123, 'session_123', 1);

        $this->reviewRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($review, $updatedReview);

        $this->reviewHelpfulRepository->shouldReceive('getUserVote')
            ->once()
            ->andReturn(null);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->reviewHelpfulRepository->shouldReceive('create')->once();
        $this->reviewRepository->shouldReceive('incrementHelpfulCount')->once()->with(1);

        $result = $this->service->markReviewHelpful(1, true, $context);

        $this->assertTrue($result->success);
        $this->assertEquals('Thank you for your feedback', $result->message);
        $this->assertEquals(6, $result->toArray()['helpful_count']);
    }

    public function testMarkReviewHelpfulRemovesSameVote()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;
        $review->helpful_count = 5;
        $review->unhelpful_count = 1;
        $review->shouldReceive('update')->once();

        $existingVote = Mockery::mock(ReviewHelpful::class)->makePartial();
        $existingVote->id = 1;
        $existingVote->is_helpful = true;

        $context = ReviewActionContext::fromAuth(123, 'session_123', 1);

        $this->reviewRepository->shouldReceive('find')->once()->andReturn($review);
        $this->reviewHelpfulRepository->shouldReceive('getUserVote')->once()->andReturn($existingVote);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($c) {
            return $c();
        });

        $this->reviewHelpfulRepository->shouldReceive('delete')->once()->with(1);

        $result = $this->service->markReviewHelpful(1, true, $context);

        $this->assertTrue($result->success);
        $this->assertEquals('Vote removed', $result->message);
    }

    public function testMarkReviewHelpfulChangesVote()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;
        $review->helpful_count = 5;
        $review->unhelpful_count = 3;
        $review->shouldReceive('update')->once();

        $existingVote = Mockery::mock(ReviewHelpful::class)->makePartial();
        $existingVote->id = 1;
        $existingVote->is_helpful = false;

        $context = ReviewActionContext::fromAuth(123, 'session_123', 1);

        $this->reviewRepository->shouldReceive('find')->once()->andReturn($review);
        $this->reviewHelpfulRepository->shouldReceive('getUserVote')->once()->andReturn($existingVote);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($c) {
            return $c();
        });

        $this->reviewHelpfulRepository->shouldReceive('update')
            ->once()
            ->with(1, ['is_helpful' => true]);

        $result = $this->service->markReviewHelpful(1, true, $context);

        $this->assertTrue($result->success);
        $this->assertEquals('Vote updated', $result->message);
    }

    public function testMarkReviewHelpfulFailsWhenReviewNotFound()
    {
        $context = ReviewActionContext::fromAuth(123, 'session_123', 1);

        $this->reviewRepository->shouldReceive('find')->once()->with(999)->andReturn(null);

        $result = $this->service->markReviewHelpful(999, true, $context);

        $this->assertFalse($result->success);
        $this->assertEquals('Review not found', $result->message);
    }

    public function testMarkReviewUnhelpfulCreatesNewVote()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->id = 1;
        $review->helpful_count = 5;
        $review->unhelpful_count = 1;
        $review->site_id = 1;

        $updatedReview = Mockery::mock(Review::class)->makePartial();
        $updatedReview->helpful_count = 5;
        $updatedReview->unhelpful_count = 2;

        $context = ReviewActionContext::fromAuth(123, 'session_123', 1);

        $this->reviewRepository->shouldReceive('find')
            ->twice()
            ->andReturn($review, $updatedReview);

        $this->reviewHelpfulRepository->shouldReceive('getUserVote')->once()->andReturn(null);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function ($c) {
            return $c();
        });

        $this->reviewHelpfulRepository->shouldReceive('create')->once();
        $this->reviewRepository->shouldReceive('incrementUnhelpfulCount')->once()->with(1);

        $result = $this->service->markReviewHelpful(1, false, $context);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->toArray()['unhelpful_count']);
    }
}