<?php

namespace App\Tests\Unit\Services\Members;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Repositories\Members\CrmMemberRepository;
use App\Repositories\Members\MemberRepository;
use App\Services\Members\CrmMemberService;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CrmMemberServiceTest extends TestCase
{
    private CrmMemberRepository|MockInterface $crmMemberRepository;

    private MemberRepository|MockInterface $memberRepository;

    private Database|MockInterface $database;

    private CrmMemberService $service;

    public function test_it_throws_exception_when_member_not_found_for_site(): void
    {
        $memberId = 10;
        $siteId = 5;

        $this->crmMemberRepository
            ->shouldReceive('findForSite')
            ->once()
            ->with($memberId, $siteId)
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Member [{$memberId}] not found for site [{$siteId}]."
        );

        $this->service->updateMember($memberId, $siteId, []);
    }

    public function test_it_throws_exception_when_email_is_already_in_use_during_update(): void
    {
        $member = $this->makeMember([
            'id' => 1,
            'email' => 'old@example.com',
        ]);

        $existingMember = $this->makeMember([
            'id' => 999,
            'email' => 'new@example.com',
        ]);

        $this->crmMemberRepository
            ->shouldReceive('findForSite')
            ->once()
            ->andReturn($member);

        $this->memberRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('new@example.com')
            ->andReturn($existingMember);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address is already in use.');

        $this->service->updateMember(
            1,
            1,
            [
                'email' => 'new@example.com',
            ]
        );
    }

    private function makeMember(array $attributes = []): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $member->{$key} = $value;
        }

        return $member;
    }

    public function test_it_updates_member_without_firing_status_or_assignment_events(): void
    {
        $member = $this->makeMember([
            'id' => 1,
            'site_id' => 10,
            'email' => 'john@example.com',
            'is_active' => true,
            'assigned_agent_id' => 7,
        ]);

        $updated = $this->makeMember([
            'id' => 1,
            'site_id' => 10,
            'email' => 'john@example.com',
            'first_name' => 'Updated',
            'is_active' => true,
            'assigned_agent_id' => 7,
        ]);

        $payload = [
            'first_name' => 'Updated',
            'invalid_field' => 'should_not_be_saved',
        ];

        $this->crmMemberRepository
            ->shouldReceive('findForSite')
            ->once()
            ->with(1, 10)
            ->andReturn($member);

        $this->memberRepository
            ->shouldReceive('findByEmail')
            ->never();

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->crmMemberRepository
            ->shouldReceive('update')
            ->once()
            ->with(
                1,
                [
                    'first_name' => 'Updated',
                ]
            )
            ->andReturn($updated);

        $result = $this->service->updateMember(1, 10, $payload);

        $this->assertSame($updated, $result);
    }

    public function test_it_resets_email_verification_when_email_changes(): void
    {
        $member = $this->makeMember([
            'id' => 1,
            'email' => 'old@example.com',
            'is_active' => true,
        ]);

        $updated = $this->makeMember([
            'id' => 1,
            'email' => 'new@example.com',
            'is_active' => true,
        ]);

        $this->crmMemberRepository
            ->shouldReceive('findForSite')
            ->once()
            ->andReturn($member);

        $this->memberRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('new@example.com')
            ->andReturn(null);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->crmMemberRepository
            ->shouldReceive('update')
            ->once()
            ->with(
                1,
                [
                    'email' => 'new@example.com',
                    'email_verified_at' => null,
                ]
            )
            ->andReturn($updated);

        $this->service->updateMember(
            1,
            1,
            [
                'email' => 'new@example.com',
            ]
        );

        $this->assertTrue(true);
    }

    public function test_it_creates_member_successfully(): void
    {
        $created = $this->makeMember([
            'id' => 100,
            'site_id' => 77,
            'email' => 'new@example.com',
        ]);

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'new@example.com',
            'invalid_field' => 'ignored',
        ];

        $this->memberRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('new@example.com')
            ->andReturn(null);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->crmMemberRepository
            ->shouldReceive('create')
            ->once()
            ->with(
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'new@example.com',
                    'site_id' => 77,
                ]
            )
            ->andReturn($created);

        $result = $this->service->createMember(77, $payload);

        $this->assertSame($created, $result);
    }

    public function test_it_throws_exception_when_email_is_already_in_use_during_create(): void
    {
        $existing = $this->makeMember([
            'id' => 500,
            'email' => 'existing@example.com',
        ]);

        $this->memberRepository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('existing@example.com')
            ->andReturn($existing);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address is already in use.');

        $this->service->createMember(
            1,
            [
                'email' => 'existing@example.com',
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->crmMemberRepository = Mockery::mock(CrmMemberRepository::class);
        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new CrmMemberService(
            $this->crmMemberRepository,
            $this->memberRepository,
            $this->database
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}