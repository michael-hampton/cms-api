<?php

namespace App\Tests\Unit\Services\Auth;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Auth\MemberResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MemberResolverTest extends FunctionalTestCase
{
    private MemberRepository $memberRepository;
    private MemberResolver $memberResolver;

    public function test_it_returns_user_when_email_exists()
    {
        // Arrange
        $email = 'test@example.com';
        $siteId = 1;

        $member = new Member();
        $member->id = 123;
        $member->email = $email;
        $member->site_id = $siteId;

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email, $siteId)
            ->willReturn($member);

        // Act
        $result = $this->memberResolver->resolveByEmail($email, $siteId);

        // Assert
        $this->assertInstanceOf(Member::class, $result);
        $this->assertEquals(123, $result->id);
        $this->assertEquals($email, $result->email);
    }

    public function test_it_returns_null_when_email_does_not_exist()
    {
        // Arrange
        $email = 'nonexistent@example.com';
        $siteId = 1;

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email, $siteId)
            ->willReturn(null);

        // Act
        $result = $this->memberResolver->resolveByEmail($email, $siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_it_normalizes_email_with_whitespace()
    {
        // Arrange
        $email = '  TEST@example.com  ';
        $normalized = 'test@example.com';
        $siteId = 1;

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($normalized, $siteId)
            ->willReturn(null);

        // Act
        $this->memberResolver->resolveByEmail($email, $siteId);

        // Assert - expectations verified by mock
    }

    public function test_it_normalizes_email_case_insensitively()
    {
        // Arrange
        $email = 'TEST@EXAMPLE.COM';
        $normalized = 'test@example.com';
        $siteId = 1;

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($normalized, $siteId)
            ->willReturn(null);

        // Act
        $this->memberResolver->resolveByEmail($email, $siteId);

        // Assert - expectations verified by mock
    }

    public function test_email_exists_returns_true_when_email_found()
    {
        // Arrange
        $email = 'existing@example.com';
        $siteId = 1;

        $member = \Mockery::mock(Member::class)->makePartial();

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email, $siteId)
            ->willReturn($member);

        // Act
        $result = $this->memberResolver->emailExists($email, $siteId);

        // Assert
        $this->assertTrue($result);
    }

    public function test_email_exists_returns_false_when_email_not_found()
    {
        // Arrange
        $email = 'new@example.com';
        $siteId = 1;

        $member = \Mockery::mock(Member::class)->makePartial();

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email, $siteId)
            ->willReturn(null);

        // Act
        $result = $this->memberResolver->emailExists($email, $siteId);

        // Assert
        $this->assertFalse($result);
    }

    public function test_it_handles_null_site_id()
    {
        // Arrange
        $email = 'test@example.com';

        $this->memberRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with($email, null)
            ->willReturn(null);

        // Act
        $result = $this->memberResolver->resolveByEmail($email, null);

        // Assert
        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = $this->createMock(MemberRepository::class);
        $this->memberResolver = new MemberResolver($this->memberRepository);
    }
}