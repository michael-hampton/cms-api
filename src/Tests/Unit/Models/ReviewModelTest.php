<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Review;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ReviewModelTest extends FunctionalTestCase
{
    protected Review $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->review = new Review([
            'product_id' => 1,
            'user_id' => 1,
            'rating' => 5,
            'title' => 'Great Product',
            'comment' => 'This is an excellent product!',
            'is_verified_purchase' => true,
            'is_approved' => true,
            'helpful_count' => 10,
            'unhelpful_count' => 2,
            'site_id' => 1
        ]);
    }

    public function testReviewCanBeInstantiated()
    {
        $this->assertInstanceOf(Review::class, $this->review);
    }

    public function testReviewHasCorrectTableName()
    {
        $this->assertEquals('reviews', $this->review->getTable());
    }

    public function testScopeApprovedAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('is_approved', true)
            ->willReturnSelf();

        $result = $this->review->scopeApproved($query);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeByProductAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('product_id', 5)
            ->willReturnSelf();

        $result = $this->review->scopeByProduct($query, 5);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeByRatingAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('rating', 4)
            ->willReturnSelf();

        $result = $this->review->scopeByRating($query, 4);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testScopeVerifiedPurchaseAddsCorrectWhereClause()
    {
        $query = $this->createMock(\App\Framework\Database\QueryBuilder::class);
        $query->expects($this->once())
            ->method('where')
            ->with('is_verified_purchase', true)
            ->willReturnSelf();

        $result = $this->review->scopeVerifiedPurchase($query);
        $this->assertInstanceOf(\App\Framework\Database\QueryBuilder::class, $result);
    }

    public function testGetAuthorNameAttributeReturnsUserName()
    {
        $user = new Member(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->review->setRelation('user', $user);

        $authorName = $this->review->getAuthorNameAttribute();
        $this->assertEquals('John Doe', $authorName);
    }

    public function testGetAuthorNameAttributeReturnsAnonymousWhenNoUser()
    {
        $this->review->setRelation('user', null);

        $authorName = $this->review->getAuthorNameAttribute();
        $this->assertEquals('Anonymous', $authorName);
    }
}