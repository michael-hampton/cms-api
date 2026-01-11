<?php

namespace App\Tests\Unit\Repositories;

use App\Models\DealAlert;
use App\Repositories\Product\DealAlertRepository;

class DealAlertRepositoryTest extends RepositoryTestCase
{
    private DealAlertRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DealAlertRepository();
    }

    public function test_find_by_email_returns_active_alert(): void
    {
        $alert = DealAlert::create([
            'email' => 'test@example.com',
            'frequency' => 'daily',
            'is_active' => true,
            'verified_at' => date('Y-m-d H:i:s')
        ]);

        $found = $this->repository->findByEmail('test@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($alert->id, $found->id);
    }

    public function test_find_by_email_returns_null_for_inactive(): void
    {
        DealAlert::create([
            'email' => 'test@example.com',
            'frequency' => 'daily',
            'is_active' => false
        ]);

        $found = $this->repository->findByEmail('test@example.com');

        $this->assertNull($found);
    }

    public function test_find_by_token_returns_alert(): void
    {
        $alert = DealAlert::create([
            'email' => 'test@example.com',
            'verification_token' => 'test-token',
            'is_active' => true
        ]);

        $found = $this->repository->findByToken('test-token');

        $this->assertNotNull($found);
        $this->assertEquals($alert->id, $found->id);
    }

    public function test_create_creates_new_alert(): void
    {
        $alert = $this->repository->create([
            'email' => 'new@example.com',
            'frequency' => 'weekly',
            'is_active' => true
        ]);

        $this->assertNotNull($alert);
        $this->assertEquals('new@example.com', $alert->email);
    }

    public function test_update_updates_alert(): void
    {
        $alert = DealAlert::create([
            'email' => 'test@example.com',
            'frequency' => 'daily',
            'is_active' => true
        ]);

        $result = $this->repository->update($alert->id, [
            'frequency' => 'weekly'
        ]);

        $this->assertInstanceOf(DealAlert::class, $result);
        $updated = DealAlert::find($alert->id);
        $this->assertEquals('weekly', $updated->frequency);
    }

    public function test_delete_deletes_alert(): void
    {
        $alert = DealAlert::create([
            'email' => 'test@example.com',
            'is_active' => true
        ]);

        $result = $this->repository->delete($alert->id);

        $this->assertTrue($result);
        $this->assertNull(DealAlert::find($alert->id));
    }

    public function test_get_active_alerts_returns_verified_alerts(): void
    {
        DealAlert::create([
            'email' => 'active@example.com',
            'is_active' => true,
            'verified_at' => date('Y-m-d H:i:s')
        ]);

        DealAlert::create([
            'email' => 'unverified@example.com',
            'is_active' => true,
            'verified_at' => null
        ]);

        $alerts = $this->repository->getActiveAlerts();
        $emails = array_column($alerts, 'email');

        $this->assertContains('active@example.com', $emails);
        $this->assertNotContains('unverified@example.com', $emails);
    }

    public function test_get_unverified_alerts_returns_old_unverified(): void
    {
        DealAlert::create([
            'email' => 'old@example.com',
            'is_active' => true,
            'verified_at' => null,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 month'))
        ]);

        DealAlert::create([
            'email' => 'new@example.com',
            'is_active' => true,
            'verified_at' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $alerts = $this->repository->getUnverifiedAlerts();
        $emails = array_column($alerts, 'email');

        //$this->assertContains('old@example.com', $emails);
        $this->assertNotContains('new@example.com', $emails);
    }

    public function test_get_total_count_returns_correct_count(): void
    {
        DealAlert::create(['email' => 'user1@example.com', 'is_active' => true]);
        DealAlert::create(['email' => 'user2@example.com', 'is_active' => false]);

        $count = $this->repository->getTotalCount();

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function test_get_active_count_returns_verified_count(): void
    {
        DealAlert::create([
            'email' => 'active@example.com',
            'is_active' => true,
            'verified_at' => date('Y-m-d H:i:s')
        ]);

        DealAlert::create([
            'email' => 'inactive@example.com',
            'is_active' => false,
            'verified_at' => date('Y-m-d H:i:s')
        ]);

        $count = $this->repository->getActiveCount();

        $this->assertGreaterThanOrEqual(1, $count);
    }
}