<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationLetterCode;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationLetterCodeService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationLetterCodeServiceTest extends TestCase
{
    private SubscriptionCommunicationRepository $communications;
    private SubscriptionCommunicationLetterCodeRepository $letterCodes;
    private SubscriptionCommunicationLetterCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communications = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->letterCodes = Mockery::mock(SubscriptionCommunicationLetterCodeRepository::class);

        $this->service = new SubscriptionCommunicationLetterCodeService(
            $this->communications,
            $this->letterCodes,
        );
    }

    public function test_create_throws_when_communication_not_found(): void
    {
        $this->communications->shouldReceive('find')->once()->with(1)->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->service->create(1, 'ACK01', null);
    }

    public function test_create_throws_when_code_already_in_use(): void
    {
        $this->communications->shouldReceive('find')->once()->andReturn(
            Mockery::mock(SubscriptionCommunication::class)->makePartial()
        );
        $this->letterCodes->shouldReceive('findByCode')->once()->with('ACK01')->andReturn(
            Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial()
        );

        $this->expectException(\RuntimeException::class);

        $this->service->create(1, 'ACK01', null);
    }

    public function test_create_throws_when_communication_already_has_a_code(): void
    {
        $this->communications->shouldReceive('find')->once()->andReturn(
            Mockery::mock(SubscriptionCommunication::class)->makePartial()
        );
        $this->letterCodes->shouldReceive('findByCode')->once()->andReturn(null);
        $this->letterCodes->shouldReceive('findForCommunication')->once()->with(1)->andReturn(
            Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial()
        );

        $this->expectException(\RuntimeException::class);

        $this->service->create(1, 'ACK01', null);
    }

    public function test_create_succeeds_when_communication_exists_and_code_is_free(): void
    {
        $this->communications->shouldReceive('find')->once()->andReturn(
            Mockery::mock(SubscriptionCommunication::class)->makePartial()
        );
        $this->letterCodes->shouldReceive('findByCode')->once()->andReturn(null);
        $this->letterCodes->shouldReceive('findForCommunication')->once()->andReturn(null);

        $expected = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $this->letterCodes->shouldReceive('create')->once()->with(1, 'ACK01', 'Welcome letter')->andReturn($expected);

        $result = $this->service->create(1, 'ACK01', 'Welcome letter');

        $this->assertSame($expected, $result);
    }

    public function test_update_throws_when_letter_code_not_found(): void
    {
        $this->letterCodes->shouldReceive('find')->once()->with(99)->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->service->update(99, 'ACK02', null);
    }

    public function test_update_allows_keeping_the_same_code(): void
    {
        $existing = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $existing->letter_code = 'ACK01';

        $this->letterCodes->shouldReceive('find')->once()->with(5)->andReturn($existing);
        $this->letterCodes->shouldReceive('findByCode')->never();

        $updated = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $this->letterCodes->shouldReceive('update')->once()->with(5, 'ACK01', 'Updated desc')->andReturn($updated);

        $result = $this->service->update(5, 'ACK01', 'Updated desc');

        $this->assertSame($updated, $result);
    }

    public function test_update_throws_when_new_code_already_in_use_by_another_row(): void
    {
        $existing = Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial();
        $existing->letter_code = 'ACK01';

        $this->letterCodes->shouldReceive('find')->once()->with(5)->andReturn($existing);
        $this->letterCodes->shouldReceive('findByCode')->once()->with('PFN01')->andReturn(
            Mockery::mock(SubscriptionCommunicationLetterCode::class)->makePartial()
        );

        $this->expectException(\RuntimeException::class);

        $this->service->update(5, 'PFN01', null);
    }

    public function test_delete_delegates_to_repository(): void
    {
        $this->letterCodes->shouldReceive('delete')->once()->with(5)->andReturn(true);

        $this->assertTrue($this->service->delete(5));
    }
}
