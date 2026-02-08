<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Models\ProductOffer;
use App\Repositories\Offers\DealClickRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Adverts\RenderContext;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class DealTrackingRecorderTest extends FunctionalTestCase
{
    use CreatesTestData;

    private DealTrackingRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $offerRepo = new ProductOfferRepository();
        $rewardDefRepo = new RewardDefinitionRepository(new RewardAuditLogRepository());
        $dealClickRepo = new DealClickRepository();
        $rewardsRepo = new RewardsRepository($rewardDefRepo, new RewardAuditLogRepository());

        $this->recorder = new DealTrackingRecorder(
            $offerRepo,
            $rewardsRepo,
            $dealClickRepo,
            $this->database
        );
    }

    public function testRecordsOfferRender(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(123, $member);
        $this->recorder->recordOfferRender($offer->id, null, $context);

        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'member_id' => $member->id,
            'action' => 'render',
        ]);
    }

    public function testRecordsOfferClick(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $context = RenderContext::forWeb(456, $member);
        $this->recorder->recordOfferClick($offer->id, null, $context);

        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'member_id' => $member->id,
            'action' => 'click',
        ]);
    }

    public function testRecordsRewardRender(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $context = RenderContext::forNewsletter(123, $member);
        $this->recorder->recordRewardRender($reward->id, null, $context, $this->siteId);

        $this->assertDatabaseHas('reward_clicks', [
            'member_reward_id' => $reward->id,
            'member_id' => $member->id,
            'action' => 'render',
        ]);
    }

    public function testRecordsRewardClick(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $context = RenderContext::forWeb(456, $member);
        $this->recorder->recordRewardClick($reward->id, null, $context, $this->siteId);

        $this->assertDatabaseHas('reward_clicks', [
            'member_reward_id' => $reward->id,
            'member_id' => $member->id,
            'action' => 'click',
        ]);
    }

    public function testRecordsRewardClaimWithTransaction(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $result = $this->recorder->recordRewardClaim($reward->id, $member->id, $this->siteId);

        $this->assertTrue($result);

        // Check reward was claimed
        $reward = $reward->fresh();
        $this->assertEquals('claimed', $reward->status);
        $this->assertNotNull($reward->claimed_at);

        // Check tracking was recorded
        $this->assertDatabaseHas('reward_clicks', [
            'member_reward_id' => $reward->id,
            'member_id' => $member->id,
            'action' => 'claim',
        ]);

        // Check audit log was created
        $this->assertDatabaseHas('reward_audit_logs', [
            'member_reward_id' => $reward->id,
            'action' => 'claimed',
        ]);
    }

    public function testRejectClaimForWrongMember(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $reward = $this->createMemberReward([
            'member_id' => $member1->id,
            'status' => 'pending',
        ]);

        $result = $this->recorder->recordRewardClaim($reward->id, $member2->id);

        $this->assertFalse($result);

        $reward = $reward->fresh();
        $this->assertEquals('pending', $reward->status);
    }

    public function testRejectClaimForAlreadyClaimed(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'claimed',
            'claimed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->recorder->recordRewardClaim($reward->id, $member->id);

        $this->assertFalse($result);
    }

    public function testRecordsDealRender(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);
        $member = $this->createMember();

        $context = RenderContext::forNewsletter(123, $member);
        $this->recorder->recordDealRender($product->id, $context, $this->siteId);

        $this->assertDatabaseHas('deal_clicks', [
            'product_id' => $product->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'action' => 'render',
            'channel' => 'newsletter',
            'surface_type' => 'newsletter_issue',
            'surface_id' => 123,
        ]);
    }

    public function testRecordsDealClick(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);
        $member = $this->createMember();

        $context = RenderContext::forWeb(456, $member);
        $this->recorder->recordDealClick($product->id, $context, $this->siteId);

        $this->assertDatabaseHas('deal_clicks', [
            'product_id' => $product->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'action' => 'click',
            'channel' => 'web',
            'surface_type' => 'page',
            'surface_id' => 456,
        ]);
    }

    public function testRecordsDealRenderWithoutMember(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(123, null);
        $this->recorder->recordDealRender($product->id, $context, $this->siteId);

        $this->assertDatabaseHas('deal_clicks', [
            'product_id' => $product->id,
            //'member_id' => null,
            'site_id' => $this->siteId,
            'action' => 'render',
        ]);
    }
}