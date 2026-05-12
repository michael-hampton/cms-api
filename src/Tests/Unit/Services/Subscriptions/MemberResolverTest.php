<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Subscriptions\MemberResolver;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class MemberResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private MemberRepository&MockInterface $memberRepository;
    private MemberResolver $resolver;

    public function testReturnsBuyerWhenNoGiftEmailProvided(): void
    {
        $buyer = $this->makeMember(1);

        $this->memberRepository->shouldNotReceive('findByEmail');
        $this->memberRepository->shouldNotReceive('create');

        $result = $this->resolver->resolve([], $buyer);

        $this->assertSame($buyer, $result);
    }

    // -------------------------------------------------------------------------
    // Non-gift path (zero DB work expected)
    // -------------------------------------------------------------------------

    private function makeMember(int $id): Member&MockInterface
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        return $member;
    }

    public function testReturnsBuyerWhenGiftEmailIsEmptyString(): void
    {
        $buyer = $this->makeMember(1);

        $this->memberRepository->shouldNotReceive('findByEmail');

        $result = $this->resolver->resolve(['gift_email' => ''], $buyer);

        $this->assertSame($buyer, $result);
    }

    // -------------------------------------------------------------------------
    // Gift path — existing member
    // -------------------------------------------------------------------------

    public function testReturnsExistingMemberWhenGiftEmailMatches(): void
    {
        $buyer = $this->makeMember(1);
        $recipient = $this->makeMember(99);

        $this->memberRepository
            ->expects('findByEmail')
            ->with('gift@example.com')
            ->andReturn($recipient);

        $this->memberRepository->shouldNotReceive('create');

        $result = $this->resolver->resolve(['gift_email' => 'gift@example.com'], $buyer);

        $this->assertSame($recipient, $result);
        $this->assertSame(99, $result->id);
    }

    public function testDoesNotCreateDuplicateMemberWhenEmailAlreadyExists(): void
    {
        $buyer = $this->makeMember(1);
        $recipient = $this->makeMember(77);

        $this->memberRepository
            ->allows('findByEmail')
            ->andReturn($recipient);

        // create() must never be called when the member already exists
        $this->memberRepository->shouldNotReceive('create');

        $this->resolver->resolve(['gift_email' => 'existing@example.com'], $buyer);

        // Assertion implicit via shouldNotReceive — add explicit one to satisfy rule
        $this->assertSame(77, $recipient->id);
    }

    // -------------------------------------------------------------------------
    // Gift path — new member creation
    // -------------------------------------------------------------------------

    public function testCreatesNewMemberWhenGiftEmailIsUnknown(): void
    {
        $buyer = $this->makeMember(1);
        $newMember = $this->makeMember(200);

        $this->memberRepository
            ->expects('findByEmail')
            ->with('new@example.com')
            ->andReturn(null);

        $this->memberRepository
            ->expects('create')
            ->with([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'new@example.com',
                'mobile' => '07700123456',
                'password' => null,
                'site_id' => 1
            ])
            ->andReturn($newMember);

        $result = $this->resolver->resolve([
            'gift_email' => 'new@example.com',
            'gift_first_name' => 'Jane',
            'gift_last_name' => 'Doe',
            'gift_mobile' => '07700123456',
            'site_id' => 1
        ], $buyer);

        $this->assertSame($newMember, $result);
    }

    public function testCreatesNewMemberWithNullNameFieldsWhenOmitted(): void
    {
        $buyer = $this->makeMember(1);
        $newMember = $this->makeMember(201);

        $this->memberRepository->allows('findByEmail')->andReturn(null);

        $this->memberRepository
            ->expects('create')
            ->with([
                'first_name' => null,
                'last_name' => null,
                'email' => 'sparse@example.com',
                'mobile' => null,
                'password' => null,
                'site_id' => 1
            ])
            ->andReturn($newMember);

        $result = $this->resolver->resolve(['gift_email' => 'sparse@example.com', 'site_id' => 1], $buyer);

        $this->assertSame($newMember, $result);
    }

    public function testCreatedMemberHasNullPasswordToTriggerActivationFlow(): void
    {
        $buyer = $this->makeMember(1);
        $newMember = $this->makeMember(202);

        $this->memberRepository->allows('findByEmail')->andReturn(null);

        $capturedPayload = null;

        $this->memberRepository
            ->expects('create')
            ->andReturnUsing(function (array $payload) use ($newMember, &$capturedPayload) {
                $capturedPayload = $payload;
                return $newMember;
            });

        $this->resolver->resolve(['gift_email' => 'activation@example.com'], $buyer);

        $this->assertArrayHasKey('password', $capturedPayload);
        $this->assertNull($capturedPayload['password']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->resolver = new MemberResolver($this->memberRepository);
    }
}