<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\Order\OrderMemberResolver;
use App\Services\Shared\NameParser;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderMemberResolverTest extends TestCase
{
    private MemberRepository $memberRepository;
    private NameParser $nameParser;
    private OrderMemberResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->nameParser = Mockery::mock(NameParser::class);

        $this->resolver = new OrderMemberResolver(
            $this->memberRepository,
            $this->nameParser
        );
    }

    public function test_it_resolves_existing_member_by_user_id()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn($member);

        $result = $this->resolver->resolve(['user_id' => 123], 1);

        $this->assertEquals($member, $result);
    }

    public function test_it_throws_exception_for_invalid_user_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID 999 not found');

        $this->memberRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->resolver->resolve(['user_id' => 999], 1);
    }

    public function test_it_returns_null_when_no_email_provided()
    {
        $result = $this->resolver->resolve([], 1);

        $this->assertNull($result);
    }

    public function test_it_finds_existing_member_by_email()
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->email = 'existing@example.com';

        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->with('existing@example.com')
            ->andReturn($member);

        $result = $this->resolver->resolve([
            'customer_email' => 'existing@example.com'
        ], 1);

        $this->assertEquals($member, $result);
    }

    public function test_it_creates_new_member_when_not_found()
    {
        $newMember = Mockery::mock(Member::class)->makePartial();
        $newMember->id = 456;

        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->with('new@example.com')
            ->andReturn(null);

        $this->nameParser->shouldReceive('parse')
            ->once()
            ->with('John Doe')
            ->andReturn([
                'first_name' => 'John',
                'last_name' => 'Doe'
            ]);

        $this->memberRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['site_id'] === 1
                    && $arg['email'] === 'new@example.com'
                    && $arg['first_name'] === 'John'
                    && $arg['last_name'] === 'Doe'
                    && isset($arg['password'])
                    && $arg['is_active'] === true;
            }))
            ->andReturn($newMember);

        $result = $this->resolver->resolve([
            'customer_email' => 'new@example.com',
            'customer_name' => 'John Doe'
        ], 1);

        $this->assertEquals($newMember, $result);
    }

    public function test_it_returns_null_when_name_parsing_fails()
    {
        $this->memberRepository->shouldReceive('findByEmail')
            ->once()
            ->andReturn(null);

        $this->nameParser->shouldReceive('parse')
            ->once()
            ->with('')
            ->andReturn([]);

        $result = $this->resolver->resolve([
            'customer_email' => 'test@example.com',
            'customer_name' => ''
        ], 1);

        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}