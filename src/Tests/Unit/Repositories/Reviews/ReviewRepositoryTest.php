<?php

namespace App\Tests\Unit\Repositories\Reviews;

use App\Models\Member;
use App\Models\Model;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubscriptionPlan;
use App\Repositories\ReviewRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

/**
 * Full integration test coverage for ReviewRepository.
 *
 * Uses the test database. Each test runs in a transaction that is rolled
 * back in tearDown via RepositoryTestCase, so tests are fully isolated.
 */
class ReviewRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ReviewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ReviewRepository();
    }

    private function createProductReview(Product $product, Member $member, array $overrides = []): Model
    {
        return Review::create(array_merge([
            'product_id' => $product->id,
            'reviewable_type' => Product::class,
            'reviewable_id' => $product->id,
            'user_id' => $member->id,
            'rating' => 4,
            'title' => 'Great product',
            'comment' => 'Really happy with this.',
            'is_verified_purchase' => false,
            'is_approved' => true,
            'helpful_count' => 0,
            'unhelpful_count' => 0,
            'site_id' => $this->siteId,
        ], $overrides));
    }

    private function createPlanReview(SubscriptionPlan $plan, Member $member, array $overrides = []): Model
    {
        return Review::create(array_merge([
            'product_id' => null,
            'reviewable_type' => SubscriptionPlan::class,
            'reviewable_id' => $plan->id,
            'user_id' => $member->id,
            'rating' => 5,
            'title' => 'Excellent subscription',
            'comment' => 'Worth every penny.',
            'is_verified_purchase' => false,
            'is_approved' => true,
            'helpful_count' => 0,
            'unhelpful_count' => 0,
            'site_id' => $this->siteId,
        ], $overrides));
    }

    // ════════════════════════════════════════════════════════════════════════
    // LEGACY PRODUCT METHODS
    // ════════════════════════════════════════════════════════════════════════

    // ─── findByProduct ─────────────────────────────────────────────────────

    public function test_findByProduct_returns_approved_reviews_for_product(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $this->createProductReview($product, $member, ['rating' => 5]);
        $this->createProductReview($product, $member, ['rating' => 3, 'is_approved' => false]);

        $result = $this->repository->findByProduct($product->id, 1, 10);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertCount(1, $result['data']);
        $this->assertEquals(5, $result['data']->first()->rating);
    }

    public function test_findByProduct_paginates_correctly(): void
    {
        $product = $this->createProduct();
        $members = array_map(fn($i) => $this->createMember(['email' => "user{$i}@example.com"]), range(1, 5));

        foreach ($members as $m) {
            $this->createProductReview($product, $m);
        }

        $page1 = $this->repository->findByProduct($product->id, 1, 2);
        $page2 = $this->repository->findByProduct($product->id, 2, 2);

        $this->assertCount(2, $page1['data']);
        $this->assertCount(2, $page2['data']);
        $this->assertEquals(5, $page1['pagination']['total']);
    }

    public function test_findByProduct_orders_by_created_at_descending(): void
    {
        $product = $this->createProduct();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $old = $this->createProductReview($product, $m1, [
            'created_at' => now_datetime()->subDays(5)->format('Y-m-d H:i:s'),
            'title' => 'Older review',
        ]);
        $new = $this->createProductReview($product, $m2, [
            'created_at' => now_datetime()->subDays(1)->format('Y-m-d H:i:s'),
            'title' => 'Newer review',
        ]);

        $result = $this->repository->findByProduct($product->id, 1, 10);

        $this->assertEquals('Newer review', $result['data']->first()->title);
    }

    public function test_findByProduct_does_not_return_reviews_for_another_product(): void
    {
        $p1 = $this->createProduct(['name' => 'P1']);
        $p2 = $this->createProduct(['name' => 'P2']);
        $m = $this->createMember();

        $this->createProductReview($p1, $m);
        $this->createProductReview($p2, $m);

        $result = $this->repository->findByProduct($p1->id, 1, 10);
        $this->assertCount(1, $result['data']);
        $this->assertEquals($p1->id, $result['data']->first()->product_id);
    }

    // ─── getAverageRating ──────────────────────────────────────────────────

    public function test_getAverageRating_returns_correct_average(): void
    {
        $product = $this->createProduct();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($product, $m1, ['rating' => 4]);
        $this->createProductReview($product, $m2, ['rating' => 2]);

        $avg = $this->repository->getAverageRating($product->id);
        $this->assertEquals(3.0, $avg);
    }

    public function test_getAverageRating_returns_zero_when_no_reviews(): void
    {
        $product = $this->createProduct();
        $this->assertEquals(0.0, $this->repository->getAverageRating($product->id));
    }

    public function test_getAverageRating_ignores_unapproved_reviews(): void
    {
        $product = $this->createProduct();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($product, $m1, ['rating' => 5]);
        $this->createProductReview($product, $m2, ['rating' => 1, 'is_approved' => false]);

        $this->assertEquals(5.0, $this->repository->getAverageRating($product->id));
    }

    public function test_getAverageRating_rounds_to_one_decimal(): void
    {
        $product = $this->createProduct();
        $members = array_map(fn($i) => $this->createMember(['email' => "u{$i}@example.com"]), range(1, 3));

        $this->createProductReview($product, $members[0], ['rating' => 5]);
        $this->createProductReview($product, $members[1], ['rating' => 4]);
        $this->createProductReview($product, $members[2], ['rating' => 3]);

        // Average = 4.0 exactly
        $avg = $this->repository->getAverageRating($product->id);
        $this->assertEquals(4.0, $avg);
    }

    // ─── getTotalReviewCount ───────────────────────────────────────────────

    public function test_getTotalReviewCount_returns_correct_count(): void
    {
        $product = $this->createProduct();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);
        $m3 = $this->createMember(['email' => 'c@example.com']);

        $this->createProductReview($product, $m1);
        $this->createProductReview($product, $m2);
        $this->createProductReview($product, $m3, ['is_approved' => false]);

        $this->assertEquals(2, $this->repository->getTotalReviewCount($product->id));
    }

    public function test_getTotalReviewCount_returns_zero_when_no_reviews(): void
    {
        $product = $this->createProduct();
        $this->assertEquals(0, $this->repository->getTotalReviewCount($product->id));
    }

    // ─── getRatingBreakdown ────────────────────────────────────────────────

    public function test_getRatingBreakdown_returns_correct_counts(): void
    {
        $product = $this->createProduct();
        $members = array_map(fn($i) => $this->createMember(['email' => "u{$i}@example.com"]), range(1, 4));

        $this->createProductReview($product, $members[0], ['rating' => 5]);
        $this->createProductReview($product, $members[1], ['rating' => 5]);
        $this->createProductReview($product, $members[2], ['rating' => 3]);
        $this->createProductReview($product, $members[3], ['rating' => 1]);

        $breakdown = $this->repository->getRatingBreakdown($product->id);

        $this->assertArrayHasKey(5, $breakdown);
        $this->assertArrayHasKey(4, $breakdown);
        $this->assertArrayHasKey(3, $breakdown);
        $this->assertArrayHasKey(2, $breakdown);
        $this->assertArrayHasKey(1, $breakdown);
        $this->assertEquals(2, $breakdown[5]);
        $this->assertEquals(0, $breakdown[4]);
        $this->assertEquals(1, $breakdown[3]);
        $this->assertEquals(0, $breakdown[2]);
        $this->assertEquals(1, $breakdown[1]);
    }

    public function test_getRatingBreakdown_returns_all_zeros_when_no_reviews(): void
    {
        $product = $this->createProduct();
        $breakdown = $this->repository->getRatingBreakdown($product->id);

        foreach ([5, 4, 3, 2, 1] as $rating) {
            $this->assertArrayHasKey($rating, $breakdown);
            $this->assertEquals(0, $breakdown[$rating]);
        }
    }

    public function test_getRatingBreakdown_ignores_unapproved_reviews(): void
    {
        $product = $this->createProduct();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($product, $m1, ['rating' => 5]);
        $this->createProductReview($product, $m2, ['rating' => 1, 'is_approved' => false]);

        $breakdown = $this->repository->getRatingBreakdown($product->id);
        $this->assertEquals(1, $breakdown[5]);
        $this->assertEquals(0, $breakdown[1]);
    }

    // ─── hasUserReviewedProduct ────────────────────────────────────────────

    public function test_hasUserReviewedProduct_returns_true_when_reviewed(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $this->createProductReview($product, $member);

        $this->assertTrue($this->repository->hasUserReviewedProduct($product->id, $member->id));
    }

    public function test_hasUserReviewedProduct_returns_false_when_not_reviewed(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $this->assertFalse($this->repository->hasUserReviewedProduct($product->id, $member->id));
    }

    public function test_hasUserReviewedProduct_is_scoped_to_member_and_product(): void
    {
        $p1 = $this->createProduct(['name' => 'P1']);
        $p2 = $this->createProduct(['name' => 'P2']);
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($p1, $m1);

        // m1 reviewed p1 — not p2
        $this->assertTrue($this->repository->hasUserReviewedProduct($p1->id, $m1->id));
        $this->assertFalse($this->repository->hasUserReviewedProduct($p2->id, $m1->id));
        // m2 reviewed nothing
        $this->assertFalse($this->repository->hasUserReviewedProduct($p1->id, $m2->id));
    }

    // ─── getVerifiedPurchaseReviews ────────────────────────────────────────

    public function test_getVerifiedPurchaseReviews_returns_only_verified(): void
    {
        $product = $this->createProduct();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($product, $m1, ['is_verified_purchase' => true]);
        $this->createProductReview($product, $m2, ['is_verified_purchase' => false]);

        $reviews = $this->repository->getVerifiedPurchaseReviews($product->id);
        $this->assertCount(1, $reviews);
        $this->assertTrue($reviews->first()->is_verified_purchase);
    }

    // ─── getTopReview ──────────────────────────────────────────────────────

    public function test_getTopReview_orders_by_helpful_count_descending(): void
    {
        $p1 = $this->createProduct(['name' => 'P1']);
        $p2 = $this->createProduct(['name' => 'P2']);
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $r1 = $this->createProductReview($p1, $m1, ['helpful_count' => 10, 'title' => 'Most helpful']);
        $r2 = $this->createProductReview($p2, $m2, ['helpful_count' => 3, 'title' => 'Less helpful']);

        $reviews = $this->repository->getTopReview([$p1->id, $p2->id]);
        $this->assertEquals('Most helpful', $reviews->first()->title);
    }

    // ─── findByUser ────────────────────────────────────────────────────────

    public function test_findByUser_returns_all_reviews_for_user(): void
    {
        $member = $this->createMember();
        $other = $this->createMember(['email' => 'other@example.com']);
        $p1 = $this->createProduct(['name' => 'P1']);
        $p2 = $this->createProduct(['name' => 'P2']);

        $this->createProductReview($p1, $member);
        $this->createProductReview($p2, $member);
        $this->createProductReview($p1, $other);

        $reviews = $this->repository->findByUser($member->id);
        $this->assertCount(2, $reviews);
    }

    public function test_findByUser_orders_by_created_at_descending(): void
    {
        $member = $this->createMember();
        $p1 = $this->createProduct(['name' => 'P1']);
        $p2 = $this->createProduct(['name' => 'P2']);

        $this->createProductReview($p1, $member, [
            'created_at' => now_datetime()->subDays(5)->format('Y-m-d H:i:s'),
            'title' => 'Older',
        ]);
        $this->createProductReview($p2, $member, [
            'created_at' => now_datetime()->subDays(1)->format('Y-m-d H:i:s'),
            'title' => 'Newer',
        ]);

        $reviews = $this->repository->findByUser($member->id);
        $this->assertEquals('Newer', $reviews->first()->title);
    }

    // ─── incrementHelpfulCount ────────────────────────────────────────────

    public function test_incrementHelpfulCount_increments_by_one(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();
        $review = $this->createProductReview($product, $member, ['helpful_count' => 3]);

        $this->repository->incrementHelpfulCount($review->id);

        $this->assertEquals(4, $this->repository->find($review->id)->helpful_count);
    }

    public function test_incrementHelpfulCount_returns_false_for_nonexistent_review(): void
    {
        $result = $this->repository->incrementHelpfulCount(99999);
        $this->assertFalse($result);
    }

    // ─── incrementUnhelpfulCount ──────────────────────────────────────────

    public function test_incrementUnhelpfulCount_increments_by_one(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();
        $review = $this->createProductReview($product, $member, ['unhelpful_count' => 1]);

        $this->repository->incrementUnhelpfulCount($review->id);

        $this->assertEquals(2, $this->repository->find($review->id)->unhelpful_count);
    }

    public function test_incrementUnhelpfulCount_returns_false_for_nonexistent_review(): void
    {
        $result = $this->repository->incrementUnhelpfulCount(99999);
        $this->assertFalse($result);
    }

    // ════════════════════════════════════════════════════════════════════════
    // POLYMORPHIC PLAN METHODS
    // ════════════════════════════════════════════════════════════════════════

    // ─── findByReviewable ──────────────────────────────────────────────────

    public function test_findByReviewable_returns_approved_reviews_for_plan(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();

        $this->createPlanReview($plan, $member, ['rating' => 5]);
        $this->createPlanReview($plan, $member, ['rating' => 2, 'is_approved' => false]);

        $result = $this->repository->findByReviewable(SubscriptionPlan::class, $plan->id, 1, 10);

        $this->assertCount(1, $result['data']);
        $this->assertEquals(5, $result['data']->first()->rating);
    }

    public function test_findByReviewable_paginates_correctly(): void
    {
        $plan = $this->createSubscriptionPlan();
        $members = array_map(fn($i) => $this->createMember(['email' => "u{$i}@plan.test"]), range(1, 5));

        foreach ($members as $m) {
            $this->createPlanReview($plan, $m);
        }

        $page1 = $this->repository->findByReviewable(SubscriptionPlan::class, $plan->id, 1, 3);
        $page2 = $this->repository->findByReviewable(SubscriptionPlan::class, $plan->id, 2, 3);

        $this->assertCount(3, $page1['data']);
        $this->assertCount(2, $page2['data']);
        $this->assertEquals(5, $page1['pagination']['total']);
    }

    public function test_findByReviewable_does_not_bleed_across_entity_types(): void
    {
        $product = $this->createProduct();
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();

        $this->createProductReview($product, $member);
        $this->createPlanReview($plan, $member);

        $planReviews = $this->repository->findByReviewable(SubscriptionPlan::class, $plan->id, 1, 10);
        $productReviews = $this->repository->findByProduct($product->id, 1, 10);

        $this->assertCount(1, $planReviews['data']);
        $this->assertCount(1, $productReviews['data']);

        // Ensure plan reviews only contain plan-type records
        $this->assertEquals(SubscriptionPlan::class, $planReviews['data']->first()->reviewable_type);
    }

    // ─── getAverageRatingForReviewable ─────────────────────────────────────

    public function test_getAverageRatingForReviewable_returns_correct_average(): void
    {
        $plan = $this->createSubscriptionPlan();
        $m1 = $this->createMember(['email' => 'a@plan.test']);
        $m2 = $this->createMember(['email' => 'b@plan.test']);

        $this->createPlanReview($plan, $m1, ['rating' => 5]);
        $this->createPlanReview($plan, $m2, ['rating' => 3]);

        $avg = $this->repository->getAverageRatingForReviewable(SubscriptionPlan::class, $plan->id);
        $this->assertEquals(4.0, $avg);
    }

    public function test_getAverageRatingForReviewable_returns_zero_when_no_reviews(): void
    {
        $plan = $this->createSubscriptionPlan();
        $this->assertEquals(0.0, $this->repository->getAverageRatingForReviewable(SubscriptionPlan::class, $plan->id));
    }

    // ─── getTotalReviewCountForReviewable ──────────────────────────────────

    public function test_getTotalReviewCountForReviewable_returns_correct_count(): void
    {
        $plan = $this->createSubscriptionPlan();
        $m1 = $this->createMember(['email' => 'a@plan.test']);
        $m2 = $this->createMember(['email' => 'b@plan.test']);
        $m3 = $this->createMember(['email' => 'c@plan.test']);

        $this->createPlanReview($plan, $m1);
        $this->createPlanReview($plan, $m2);
        $this->createPlanReview($plan, $m3, ['is_approved' => false]);

        $count = $this->repository->getTotalReviewCountForReviewable(SubscriptionPlan::class, $plan->id);
        $this->assertEquals(2, $count);
    }

    // ─── getRatingBreakdownForReviewable ───────────────────────────────────

    public function test_getRatingBreakdownForReviewable_returns_all_five_keys(): void
    {
        $plan = $this->createSubscriptionPlan();
        $breakdown = $this->repository->getRatingBreakdownForReviewable(SubscriptionPlan::class, $plan->id);

        $this->assertArrayHasKey(5, $breakdown);
        $this->assertArrayHasKey(4, $breakdown);
        $this->assertArrayHasKey(3, $breakdown);
        $this->assertArrayHasKey(2, $breakdown);
        $this->assertArrayHasKey(1, $breakdown);
    }

    public function test_getRatingBreakdownForReviewable_counts_correctly(): void
    {
        $plan = $this->createSubscriptionPlan();
        $members = array_map(fn($i) => $this->createMember(['email' => "u{$i}@plan.test"]), range(1, 3));

        $this->createPlanReview($plan, $members[0], ['rating' => 5]);
        $this->createPlanReview($plan, $members[1], ['rating' => 5]);
        $this->createPlanReview($plan, $members[2], ['rating' => 2]);

        $breakdown = $this->repository->getRatingBreakdownForReviewable(SubscriptionPlan::class, $plan->id);
        $this->assertEquals(2, $breakdown[5]);
        $this->assertEquals(0, $breakdown[4]);
        $this->assertEquals(0, $breakdown[3]);
        $this->assertEquals(1, $breakdown[2]);
        $this->assertEquals(0, $breakdown[1]);
    }

    // ─── hasUserReviewedReviewable ─────────────────────────────────────────

    public function test_hasUserReviewedReviewable_returns_true_when_reviewed(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();

        $this->createPlanReview($plan, $member);

        $this->assertTrue(
            $this->repository->hasUserReviewedReviewable(SubscriptionPlan::class, $plan->id, $member->id)
        );
    }

    public function test_hasUserReviewedReviewable_returns_false_when_not_reviewed(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();

        $this->assertFalse(
            $this->repository->hasUserReviewedReviewable(SubscriptionPlan::class, $plan->id, $member->id)
        );
    }

    public function test_hasUserReviewedReviewable_is_scoped_by_type_and_id(): void
    {
        $plan1 = $this->createSubscriptionPlan(['name' => 'Plan A']);
        $plan2 = $this->createSubscriptionPlan(['name' => 'Plan B']);
        $member = $this->createMember();

        $this->createPlanReview($plan1, $member);

        $this->assertTrue(
            $this->repository->hasUserReviewedReviewable(SubscriptionPlan::class, $plan1->id, $member->id)
        );
        $this->assertFalse(
            $this->repository->hasUserReviewedReviewable(SubscriptionPlan::class, $plan2->id, $member->id)
        );
    }

    public function test_hasUserReviewedReviewable_does_not_confuse_entity_types(): void
    {
        $plan = $this->createSubscriptionPlan();
        $product = $this->createProduct();
        $member = $this->createMember();

        // The member reviewed the product, NOT the plan
        $this->createProductReview($product, $member);

        // The plan review check should return false
        $this->assertFalse(
            $this->repository->hasUserReviewedReviewable(SubscriptionPlan::class, $plan->id, $member->id)
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // ISOLATION: plan reviews must not affect product queries and vice versa
    // ════════════════════════════════════════════════════════════════════════

    public function test_product_average_is_not_affected_by_plan_reviews(): void
    {
        $product = $this->createProduct();
        $plan = $this->createSubscriptionPlan();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($product, $m1, ['rating' => 4]);
        $this->createPlanReview($plan, $m2, ['rating' => 1]); // should not affect product avg

        $this->assertEquals(4.0, $this->repository->getAverageRating($product->id));
    }

    public function test_plan_average_is_not_affected_by_product_reviews(): void
    {
        $product = $this->createProduct();
        $plan = $this->createSubscriptionPlan();
        $m1 = $this->createMember(['email' => 'a@example.com']);
        $m2 = $this->createMember(['email' => 'b@example.com']);

        $this->createProductReview($product, $m1, ['rating' => 1]); // should not affect plan avg
        $this->createPlanReview($plan, $m2, ['rating' => 5]);

        $this->assertEquals(
            5.0,
            $this->repository->getAverageRatingForReviewable(SubscriptionPlan::class, $plan->id)
        );
    }
}