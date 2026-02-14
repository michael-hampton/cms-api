<?php

namespace App\Tests\Unit\Services\Reviews;

use App\Models\Review;
use App\Services\Reviews\ReviewPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReviewPolicyTest extends TestCase
{
    private ReviewPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReviewPolicy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCanCreateReturnsTrueWhenAuthenticated()
    {
        $result = $this->policy->canCreate(123, 1);
        $this->assertTrue($result);
    }

    public function testCanCreateReturnsFalseWhenNotAuthenticated()
    {
        $result = $this->policy->canCreate(null, 1);
        $this->assertFalse($result);
    }

    public function testCanEditReturnsTrueForOwner()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $result = $this->policy->canEdit($review, 123);
        $this->assertTrue($result);
    }

    public function testCanEditReturnsFalseForNonOwner()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $result = $this->policy->canEdit($review, 456);
        $this->assertFalse($result);
    }

    public function testCanDeleteReturnsTrueForOwner()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $result = $this->policy->canDelete($review, 123);
        $this->assertTrue($result);
    }

    public function testCanDeleteReturnsFalseForNonOwner()
    {
        $review = Mockery::mock(Review::class)->makePartial();
        $review->user_id = 123;

        $result = $this->policy->canDelete($review, 456);
        $this->assertFalse($result);
    }

    public function testCanVoteAlwaysReturnsTrue()
    {
        $this->assertTrue($this->policy->canVote(123, 'session_123'));
        $this->assertTrue($this->policy->canVote(null, 'session_456'));
    }
}