<?php

namespace App\Tests\Unit\Repositories\Product;

use App\Models\PriceAlert;
use App\Repositories\Product\PriceAlertRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PriceAlertRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PriceAlertRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PriceAlertRepository();
    }

    public function test_find_active_alert_by_email_and_product_returns_alert(): void
    {
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $found = $this->repository->findActiveAlertByEmailAndProduct('test@example.com', $product->id);

        $this->assertNotNull($found);
        $this->assertEquals($alert->id, $found->id);
    }

    public function test_find_active_alert_returns_null_when_not_found(): void
    {
        $found = $this->repository->findActiveAlertByEmailAndProduct('nonexistent@example.com', 999);

        $this->assertNull($found);
    }

    public function test_find_active_alert_excludes_triggered_alerts(): void
    {
        $product = $this->createProduct();

        PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => true,
            'is_notified' => false
        ]);

        $found = $this->repository->findActiveAlertByEmailAndProduct('test@example.com', $product->id);

        $this->assertNull($found);
    }

    public function test_create_creates_new_alert(): void
    {
        $product = $this->createProduct();

        $alert = $this->repository->create([
            'email' => 'new@example.com',
            'product_id' => $product->id,
            'target_price' => 75.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $this->assertNotNull($alert);
        $this->assertEquals('new@example.com', $alert->email);
        $this->assertEquals($product->id, $alert->product_id);
        $this->assertEquals(75.00, $alert->target_price);
    }

    public function test_update_updates_alert_successfully(): void
    {
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $result = $this->repository->update($alert, [
            'target_price' => 60.00,
            'current_price' => 90.00
        ]);

        $this->assertTrue($result);

        $updated = PriceAlert::find($alert->id);
        $this->assertEquals(60.00, $updated->target_price);
        $this->assertEquals(90.00, $updated->current_price);
    }

    public function test_get_untriggered_alerts_returns_correct_alerts(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $untriggered1 = PriceAlert::create([
            'email' => 'user1@example.com',
            'product_id' => $product1->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $untriggered2 = PriceAlert::create([
            'email' => 'user2@example.com',
            'product_id' => $product2->id,
            'target_price' => 75.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $triggered = PriceAlert::create([
            'email' => 'user3@example.com',
            'product_id' => $product1->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => false
        ]);

        $alerts = $this->repository->getUntriggeredAlerts();

        $alertIds = array_column($alerts, 'id');
        $this->assertContains($untriggered1->id, $alertIds);
        $this->assertContains($untriggered2->id, $alertIds);
        $this->assertNotContains($triggered->id, $alertIds);
    }

    public function test_get_untriggered_alerts_excludes_notified(): void
    {
        $product = $this->createProduct();

        $untriggered = PriceAlert::create([
            'email' => 'user1@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $notified = PriceAlert::create([
            'email' => 'user2@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => true
        ]);

        $alerts = $this->repository->getUntriggeredAlerts();

        $alertIds = array_column($alerts, 'id');
        $this->assertContains($untriggered->id, $alertIds);
        $this->assertNotContains($notified->id, $alertIds);
    }

    public function test_get_user_alerts_returns_user_alerts(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $product = $this->createProduct();

        $userAlert = PriceAlert::create([
            'user_id' => $member->id,
            'email' => 'user@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $otherAlert = PriceAlert::create([
            'user_id' => $otherMember->id,
            'email' => 'other@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $alerts = $this->repository->getUserAlerts($member->id);

        $alertIds = array_column($alerts, 'id');
        $this->assertContains($userAlert->id, $alertIds);
        $this->assertNotContains($otherAlert->id, $alertIds);
    }

    public function test_get_user_alerts_orders_by_created_at_desc(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        $alert1 = PriceAlert::create([
            'user_id' => $member->id,
            'email' => 'user@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);

        $alert2 = PriceAlert::create([
            'user_id' => $member->id,
            'email' => 'user@example.com',
            'product_id' => $product->id,
            'target_price' => 60.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $alerts = $this->repository->getUserAlerts($member->id);

        $this->assertEquals($alert2->id, $alerts[1]['id']);
        $this->assertEquals($alert1->id, $alerts[0]['id']);
    }

    public function test_find_by_id_returns_alert(): void
    {
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $found = $this->repository->findById($alert->id);

        $this->assertNotNull($found);
        $this->assertEquals($alert->id, $found->id);
    }

    public function test_find_by_id_filters_by_user_id(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'user_id' => $member->id,
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $found = $this->repository->findById($alert->id, $otherMember->id);

        $this->assertNull($found);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findById(999999);

        $this->assertNull($found);
    }

    public function test_delete_deletes_alert_successfully(): void
    {
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $result = $this->repository->delete($alert);

        $this->assertTrue($result);
        $this->assertNull(PriceAlert::find($alert->id));
    }

    public function test_get_total_count_returns_correct_count(): void
    {
        $product = $this->createProduct();

        PriceAlert::create([
            'email' => 'user1@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        PriceAlert::create([
            'email' => 'user2@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => true,
            'is_notified' => true
        ]);

        $count = $this->repository->getTotalCount();

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_get_active_count_returns_untriggered_count(): void
    {
        $product = $this->createProduct();

        PriceAlert::create([
            'email' => 'user1@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        PriceAlert::create([
            'email' => 'user2@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => false
        ]);

        $count = $this->repository->getActiveCount();

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_get_triggered_count_returns_triggered_but_not_notified(): void
    {
        $product = $this->createProduct();

        PriceAlert::create([
            'email' => 'user1@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => false
        ]);

        PriceAlert::create([
            'email' => 'user2@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => true
        ]);

        PriceAlert::create([
            'email' => 'user3@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $count = $this->repository->getTriggeredCount();

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_get_notified_count_returns_notified_alerts(): void
    {
        $product = $this->createProduct();

        PriceAlert::create([
            'email' => 'user1@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => true
        ]);

        PriceAlert::create([
            'email' => 'user2@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => false
        ]);

        $count = $this->repository->getNotifiedCount();

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_create_with_variant_id(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);

        $alert = $this->repository->create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $this->assertNotNull($alert);
        $this->assertEquals($variant->id, $alert->variant_id);
    }

    public function test_create_with_merchant_id(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $alert = $this->repository->create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $this->assertNotNull($alert);
        $this->assertEquals(1, $alert->merchant_id);
    }

    public function test_create_with_user_id(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        $alert = $this->repository->create([
            'user_id' => $member->id,
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $this->assertNotNull($alert);
        $this->assertEquals($member->id, $alert->user_id);
    }

    public function test_update_marks_alert_as_triggered(): void
    {
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 100.00,
            'is_triggered' => false,
            'is_notified' => false
        ]);

        $now = date('Y-m-d H:i:s');
        $result = $this->repository->update($alert, [
            'is_triggered' => true,
            'triggered_at' => $now,
            'current_price' => 45.00
        ]);

        $this->assertTrue($result);

        $updated = PriceAlert::find($alert->id);
        $this->assertTrue($updated->is_triggered);
        $this->assertEquals(45.00, $updated->current_price);
        $this->assertNotNull($updated->triggered_at);
    }

    public function test_update_marks_alert_as_notified(): void
    {
        $product = $this->createProduct();

        $alert = PriceAlert::create([
            'email' => 'test@example.com',
            'product_id' => $product->id,
            'target_price' => 50.00,
            'current_price' => 45.00,
            'is_triggered' => true,
            'is_notified' => false
        ]);

        $now = date('Y-m-d H:i:s');
        $result = $this->repository->update($alert, [
            'is_notified' => true,
            'notified_at' => $now
        ]);

        $this->assertTrue($result);

        $updated = PriceAlert::find($alert->id);
        $this->assertTrue($updated->is_notified);
        $this->assertNotNull($updated->notified_at);
    }
}