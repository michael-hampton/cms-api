<?php

namespace App\Tests\Unit\Services;

use App\Models\DealAlert;
use App\Repositories\Product\DealAlertRepository;
use App\Services\Product\DealAlertService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class DealAlertServiceTest extends FunctionalTestCase
{
    private DealAlertService $service;
    private $dealAlertRepository;

    protected function setUp(): void
    {
        $this->dealAlertRepository = m::mock(DealAlertRepository::class);
        parent::setUp();
        $this->service = new DealAlertService($this->dealAlertRepository);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testSubscribeFailsWithInvalidEmail()
    {
        $data = ['email' => 'invalid-email'];
        $result = $this->service->subscribe($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid email is required', $result['message']);
    }

    public function testSubscribeFailsWithMissingEmail()
    {
        $data = [];
        $result = $this->service->subscribe($data);

        $this->assertFalse($result['success']);
        $this->assertEquals('Valid email is required', $result['message']);
    }

    public function testSubscribeSuccessfullyCreatesNewAlert()
    {
        $mockAlert = m::mock(DealAlert::class)->makePartial();
        $mockAlert->id = 1;
        $mockAlert->email = 'test@example.com';
        $mockAlert->verification_token = 'test-token';

        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn(null);

        $this->dealAlertRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['email'] === 'test@example.com'
                    && $data['is_active'] === true
                    && $data['frequency'] === 'daily';
            }))
        ->andReturn($mockAlert);

        $mockAlert->shouldReceive('isVerified')->andReturn(false);

        $data = ['email' => 'test@example.com'];
        $result = $this->service->subscribe($data);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('created', $result['message']);
    }

    public function testSubscribeResendsVerificationForUnverifiedEmail()
    {
        $existingAlert = m::mock(DealAlert::class)->makePartial();
        $existingAlert->email = 'test@example.com';
        $existingAlert->verification_token = 'existing-token';
        $existingAlert->shouldReceive('isVerified')->andReturn(false);

        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn($existingAlert);

        $data = ['email' => 'test@example.com'];
        $result = $this->service->subscribe($data);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('resent', $result['message']);
    }

    public function testSubscribeFailsForAlreadyVerifiedEmail()
    {
        $existingAlert = m::mock(DealAlert::class)->makePartial();
        $existingAlert->email = 'test@example.com';
        $existingAlert->shouldReceive('isVerified')->andReturn(true);

        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn($existingAlert);

        $data = ['email' => 'test@example.com'];
        $result = $this->service->subscribe($data);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already subscribed', $result['message']);
    }

    public function testVerifySuccessfullyVerifiesToken()
    {
        $alert = m::mock(DealAlert::class)->makePartial();
        $alert->id = 1;
        $alert->shouldReceive('isVerified')->andReturn(false);
        $alert->shouldReceive('update')->andReturn(true);

        $this->dealAlertRepository->shouldReceive('findByToken')
            ->once()
            ->with('valid-token')
            ->andReturn($alert);

        $this->dealAlertRepository->shouldReceive('update')
            ->once()
            ->with($alert->id, m::on(function ($data) {
                return $data['verified_at'] !== null;
            }))
        ->andReturn($alert);

        $result = $this->service->verify('valid-token');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('verified', $result['message']);
    }

    public function testVerifyFailsWithInvalidToken()
    {
        $this->dealAlertRepository->shouldReceive('findByToken')
            ->once()
            ->with('invalid-token')
            ->andReturn(null);

        $result = $this->service->verify('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid verification token', $result['message']);
    }

    public function testVerifyFailsForAlreadyVerifiedAlert()
    {
        $alert = m::mock(DealAlert::class)->makePartial();
        $alert->shouldReceive('isVerified')->andReturn(true);

        $this->dealAlertRepository->shouldReceive('findByToken')
            ->once()
            ->with('token')
            ->andReturn($alert);

        $result = $this->service->verify('token');

        $this->assertFalse($result['success']);
        $this->assertEquals('Email already verified', $result['message']);
    }

    public function testUnsubscribeSuccessfully()
    {
        $alert = m::mock(DealAlert::class)->makePartial();
        $alert->id = 1;
        $alert->shouldReceive('update')->andReturn(true);

        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn($alert);

        $this->dealAlertRepository->shouldReceive('update')
            ->once()
            ->with($alert->id, m::on(function ($data) {
                return $data['is_active'] === false;
            }))
            ->andReturn($alert);

        $result = $this->service->unsubscribe('test@example.com');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('unsubscribed', $result['message']);
    }

    public function testUnsubscribeFailsWhenNotFound()
    {
        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('notfound@example.com')
            ->andReturn(null);

        $result = $this->service->unsubscribe('notfound@example.com');

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription not found', $result['message']);
    }

    public function testUpdatePreferencesSuccessfully()
    {
        $alert = m::mock(DealAlert::class)->makePartial();
        $alert->frequency = 'daily';
        $alert->id = 1;
        $alert->preferences = null;

        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn($alert);

        $this->dealAlertRepository->shouldReceive('update')
            ->once()
            ->with($alert->id, m::on(function ($data) {
                return $data['frequency'] === 'weekly'
                    && $data['preferences'] === ['categories' => [1, 2, 3]];
            }))
            ->andReturn($alert);

        $preferences = [
            'frequency' => 'weekly',
            'preferences' => ['categories' => [1, 2, 3]]
        ];

        $result = $this->service->updatePreferences('test@example.com', $preferences);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('updated', $result['message']);
    }

    public function testUpdatePreferencesFailsWhenNotFound()
    {
        $this->dealAlertRepository->shouldReceive('findByEmail')
            ->once()
            ->with('notfound@example.com')
            ->andReturn(null);

        $result = $this->service->updatePreferences('notfound@example.com', []);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription not found', $result['message']);
    }

    public function testGetAlertStatsReturnsCorrectStats()
    {
        $this->dealAlertRepository->shouldReceive('getTotalCount')
            ->once()
            ->andReturn(100);

        $this->dealAlertRepository->shouldReceive('getActiveCount')
            ->once()
            ->andReturn(75);

        $stats = $this->service->getAlertStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_alerts', $stats);
        $this->assertArrayHasKey('active_alerts', $stats);
        $this->assertEquals(100, $stats['total_alerts']);
        $this->assertEquals(75, $stats['active_alerts']);
    }
}