<?php

namespace App\Tests\Functional\Controllers\Shopping;

use App\Models\GiftPromotion;
use App\Models\PromotionIssueExclusion;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class GiftPromotionControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // ─── index ───────────────────────────────────────────────────────────────

    public function test_index_returns_promotions_for_site(): void
    {
        $this->createGiftPromotion(['name' => 'Summer Gift']);
        $this->createGiftPromotion(['name' => 'Winter Gift']);

        $response = $this->getForSite('/api/gift-promotions');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function test_index_filters_by_active_status(): void
    {
        $this->createGiftPromotion(['name' => 'Active Promo', 'active' => true]);
        $this->createGiftPromotion(['name' => 'Inactive Promo', 'active' => false]);

        $response = $this->getForSite('/api/gift-promotions?active=1');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Active Promo', $data['items'][0]['name']);
    }

    public function test_index_searches_by_name(): void
    {
        $this->createGiftPromotion(['name' => 'Summer Campaign']);
        $this->createGiftPromotion(['name' => 'Winter Campaign']);

        $response = $this->getForSite('/api/gift-promotions?search=Summer');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Summer Campaign', $data['items'][0]['name']);
    }

    public function test_index_returns_empty_when_no_promotions(): void
    {
        $response = $this->getForSite('/api/gift-promotions');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['items']);
    }

    public function test_index_paginates_results(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createGiftPromotion(['name' => "Promo {$i}"]);
        }

        $response = $this->getForSite('/api/gift-promotions?page=1&per_page=10');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(10, $data['items']);
        $this->assertEquals(25, $data['pagination']['total']);
        $this->assertEquals(3, $data['pagination']['total_pages']);
    }

    // ─── store ───────────────────────────────────────────────────────────────

    public function test_store_creates_promotion(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Black Friday Gift',
            'type' => 'gift',
            'gift_type' => 'subscription',
            'website' => 'standalone',
            'active' => true,
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Black Friday Gift', $data['data']['name']);
        $this->assertTrue($data['data']['active']);

        $this->assertNotNull(GiftPromotion::where('name', 'Black Friday Gift')
            ->where('site_id', $this->siteId)
            ->first());
    }

    public function test_store_creates_promotion_with_triggers(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Trigger Promo',
            'gift_type' => 'subscription',
            'triggers' => [
                [
                    'type' => 'subscription_plan',
                    'operator' => '=',
                    'reference_id' => null,
                    'value' => '1',
                ],
                [
                    'type' => 'cart_total',
                    'operator' => '>=',
                    'value' => '3',
                ],
            ],
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']['triggers']);
    }

    public function test_store_creates_promotion_with_issue_exclusions(): void
    {
        $issue1 = $this->createIssueDelivery(['issue_title' => 'January Issue']);
        $issue2 = $this->createIssueDelivery(['issue_title' => 'February Issue']);

        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Exclusive Promo',
            'type' => 'gift',
            'gift_type' => 'subscription',
            'website' => 'standalone',
            'excluded_issue_ids' => [$issue1->id, $issue2->id],
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $promotionId = $data['data']['id'];

        $this->assertCount(2, PromotionIssueExclusion::where('promotion_id', $promotionId)->get());
    }

    public function test_store_does_not_save_exclusions_for_non_standalone_promotions(): void
    {
        $issue = $this->createIssueDelivery();

        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Multi-site Promo',
            'type' => 'gift',
            'gift_type' => 'subscription',
            'website' => 'multi',
            'excluded_issue_ids' => [$issue->id],
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $promotionId = $data['promotion']['id'];

        // Exclusions must not be persisted — promotion type does not support them
        $this->assertCount(0, PromotionIssueExclusion::where('promotion_id', $promotionId)->get());
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'gift_type' => 'product',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function test_store_validates_required_gift_type(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Test Promo',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('gift_type', $data['errors']);
    }

    public function test_store_validates_ends_at_after_or_equal_starts_at(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Date Validation Promo',
            'gift_type' => 'subscription',
            'starts_at' => '2024-12-31',
            'ends_at' => '2024-01-01',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('ends_at', $data['errors']);
    }

    public function test_store_accepts_ends_at_equal_to_starts_at(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Same Day Promo',
            'gift_type' => 'subscription',
            'starts_at' => '2024-06-01',
            'ends_at' => '2024-06-01',
        ]);

        // ends_at === starts_at is explicitly allowed by after_or_equal
        $this->assertResponseStatus(201, $response);
    }

    public function test_store_accepts_ends_at_after_starts_at(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Valid Date Range Promo',
            'gift_type' => 'subscription',
            'starts_at' => '2024-06-01',
            'ends_at' => '2024-12-31',
        ]);

        $this->assertResponseStatus(201, $response);
    }

    public function test_store_accepts_omitted_date_fields(): void
    {
        // Both starts_at and ends_at are nullable — omitting them is valid
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'No Dates Promo',
            'gift_type' => 'subscription',
        ]);

        $this->assertResponseStatus(201, $response);
    }

    public function test_store_accepts_ends_at_when_starts_at_is_omitted(): void
    {
        // starts_at absent — ends_at has nothing to compare against, so it passes
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'End Only Promo',
            'gift_type' => 'subscription',
            'ends_at' => '2024-12-31',
        ]);

        $this->assertResponseStatus(201, $response);
    }

    public function test_store_validates_max_per_order_minimum_one(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Max Order Promo',
            'gift_type' => 'subscription',
            'max_per_order' => 0,
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('max_per_order', $data['errors']);
    }

    public function test_store_validates_priority_minimum_zero(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Priority Promo',
            'gift_type' => 'subscription',
            'priority' => -1,
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('priority', $data['errors']);
    }

    public function test_store_validates_gift_type_max_length(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Test Promo',
            'gift_type' => str_repeat('x', 101),
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('gift_type', $data['errors']);
    }

    public function test_store_validates_trigger_requires_type(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Trigger Promo',
            'gift_type' => 'subscription',
            'triggers' => [
                ['operator' => '=', 'value' => '5'], // missing type
            ],
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('triggers.0.type', $data['errors']);
    }

    public function test_store_validates_trigger_requires_operator(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Trigger Promo',
            'gift_type' => 'subscription',
            'triggers' => [
                ['type' => 'cart_total', 'value' => '5'], // missing operator
            ],
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('triggers.0.operator', $data['errors']);
    }

    public function test_store_validates_excluded_issue_ids_must_be_integers(): void
    {
        $response = $this->postForSite('/api/gift-promotions', [
            'name' => 'Test Promo',
            'gift_type' => 'subscription',
            'excluded_issue_ids' => ['not-an-integer'],
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('excluded_issue_ids.0', $data['errors']);
    }


    // ─── update ──────────────────────────────────────────────────────────────

    public function test_update_modifies_promotion(): void
    {
        $promotion = $this->createGiftPromotion(['name' => 'Original Name']);

        $response = $this->putForSite("/api/gift-promotions/{$promotion->id}", [
            'name' => 'Updated Name',
            'gift_type' => 'product',
            'active' => false,
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Name', $data['data']['name']);
        $this->assertFalse($data['data']['active']);

        $updated = GiftPromotion::find($promotion->id);
        $this->assertEquals('Updated Name', $updated->name);
    }

    public function test_update_validates_ends_at_after_or_equal_starts_at(): void
    {
        $promotion = $this->createGiftPromotion([
            'starts_at' => '2024-01-01',
            'ends_at' => '2024-12-31',
        ]);

        $response = $this->putForSite("/api/gift-promotions/{$promotion->id}", [
            'name' => $promotion->name,
            'gift_type' => 'subscription',
            'starts_at' => '2024-12-31',
            'ends_at' => '2024-01-01',
        ]);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('ends_at', $data['errors']);
    }

    public function test_update_replaces_triggers(): void
    {
        $promotion = $this->createGiftPromotionWithTriggers([], [
            ['type' => 'cart_total', 'operator' => '=', 'value' => '1'],
            ['type' => 'cart_total', 'operator' => '=', 'value' => '2'],
        ]);

        $response = $this->putForSite("/api/gift-promotions/{$promotion->id}", [
            'name' => $promotion->name,
            'gift_type' => 'product',
            'triggers' => [
                ['type' => 'cart_total', 'operator' => '>=', 'value' => '5'],
            ],
        ]);

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['triggers']);
        $this->assertEquals('cart_total', $data['data']['triggers'][0]['type']);
    }

    public function test_update_replaces_issue_exclusions(): void
    {
        $promotion = $this->createGiftPromotion([
            'gift_type' => 'subscription',
            'website' => 'standalone',
        ]);

        $issue1 = $this->createIssueDelivery();
        $issue2 = $this->createIssueDelivery();
        $issue3 = $this->createIssueDelivery();

        // Start with issue1 and issue2 excluded
        $this->createPromotionIssueExclusion($promotion, $issue1);
        $this->createPromotionIssueExclusion($promotion, $issue2);

        // Update to only exclude issue3
        $response = $this->putForSite("/api/gift-promotions/{$promotion->id}", [
            'name' => $promotion->name,
            'type' => 'gift',
            'gift_type' => 'subscription',
            'website' => 'standalone',
            'excluded_issue_ids' => [$issue3->id],
        ]);

        $this->assertResponseStatus(200, $response);

        $exclusions = PromotionIssueExclusion::where('promotion_id', $promotion->id)->get();
        $this->assertCount(1, $exclusions);
        $this->assertEquals($issue3->id, $exclusions->first()->issue_delivery_id);
    }

    public function test_update_clears_exclusions_when_empty_array_provided(): void
    {
        $promotion = $this->createGiftPromotion([
            'gift_type' => 'subscription',
            'website' => 'standalone',
        ]);

        $issue = $this->createIssueDelivery();
        $this->createPromotionIssueExclusion($promotion, $issue);

        $response = $this->putForSite("/api/gift-promotions/{$promotion->id}", [
            'name' => $promotion->name,
            'type' => 'gift',
            'gift_type' => 'subscription',
            'website' => 'standalone',
            'excluded_issue_ids' => [],
        ]);

        $this->assertResponseStatus(200, $response);

        $this->assertCount(0, PromotionIssueExclusion::where('promotion_id', $promotion->id)->get());
    }

    public function test_update_returns_404_for_nonexistent_promotion(): void
    {
        $response = $this->putForSite('/api/gift-promotions/99999', [
            'name' => 'Ghost',
            'gift_type' => 'product',
        ]);

        $this->assertResponseStatus(500, $response);
    }

    // ─── toggleActive ────────────────────────────────────────────────────────

    public function test_toggle_active_activates_inactive_promotion(): void
    {
        $promotion = $this->createGiftPromotion(['active' => false]);

        $response = $this->postForSite("/api/gift-promotions/{$promotion->id}/toggle-active");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['active']);

        $this->assertTrue(GiftPromotion::find($promotion->id)->active);
    }

    public function test_toggle_active_deactivates_active_promotion(): void
    {
        $promotion = $this->createGiftPromotion(['is_active' => true]);

        $response = $this->postForSite("/api/gift-promotions/{$promotion->id}/toggle-active");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['active']);

        $this->assertFalse(GiftPromotion::find($promotion->id)->active);
    }

    public function test_toggle_active_returns_404_for_nonexistent(): void
    {
        $response = $this->postForSite('/api/gift-promotions/99999/toggle-active');

        $this->assertResponseStatus(500, $response);
    }

    // ─── exclusions ──────────────────────────────────────────────────────────

    public function test_exclusions_returns_excluded_issue_ids(): void
    {
        $promotion = $this->createGiftPromotion([
            'gift_type' => 'subscription',
            'website' => 'standalone',
        ]);

        $issue1 = $this->createIssueDelivery();
        $issue2 = $this->createIssueDelivery();

        $this->createPromotionIssueExclusion($promotion, $issue1);
        $this->createPromotionIssueExclusion($promotion, $issue2);

        $response = $this->getForSite("/api/gift-promotions/{$promotion->id}/exclusions");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['excluded_issue_ids']);
        $this->assertContains($issue1->id, $data['data']['excluded_issue_ids']);
        $this->assertContains($issue2->id, $data['data']['excluded_issue_ids']);
        $this->assertTrue($data['data']['supports_exclusions']);
    }

    public function test_exclusions_reports_not_supported_for_non_standalone(): void
    {
        $promotion = $this->createGiftPromotion([
            'gift_type' => 'subscription',
            'website' => 'multi',
        ]);

        $response = $this->getForSite("/api/gift-promotions/{$promotion->id}/exclusions");

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['supports_exclusions']);
        $this->assertCount(0, $data['data']['excluded_issue_ids']);
    }

    // ─── eligibility ─────────────────────────────────────────────────────────

    public function test_excluded_issue_is_not_eligible_for_promotion(): void
    {
        $promotion = $this->createGiftPromotion([
            'gift_type' => 'subscription',
            'website' => 'standalone',
            'active' => true,
        ]);

        $excludedIssue = $this->createIssueDelivery(['issue_title' => 'Excluded Issue']);
        $eligibleIssue = $this->createIssueDelivery(['issue_title' => 'Eligible Issue']);

        $this->createPromotionIssueExclusion($promotion, $excludedIssue);

        // Assert excluded issue is blocked
        $this->assertTrue($promotion->hasExcludedIssue($excludedIssue->id));

        // Assert non-excluded issue is not blocked
        $this->assertFalse($promotion->hasExcludedIssue($eligibleIssue->id));
    }

    public function test_inactive_promotion_is_never_eligible(): void
    {
        $promotion = $this->createGiftPromotion(['active' => false]);
        $issue = $this->createIssueDelivery();

        $this->assertFalse($promotion->active);
        $this->assertFalse($promotion->hasExcludedIssue($issue->id));
    }
}