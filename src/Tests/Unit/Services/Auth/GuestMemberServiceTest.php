<?php

namespace App\Tests\Unit\Services\Auth;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Auth\GuestMemberService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class GuestMemberServiceTest extends FunctionalTestCase
{
    private MemberRepository $userRepository;
    private GuestMemberService $guestUserService;

    public function test_it_creates_anonymous_member()
    {
        // Arrange
        $email = 'guest@example.com';
        $siteId = 1;

        $user = new Member();
        $user->id = 456;
        $user->email = $email;
        $user->anonymous = true;

        $this->userRepository
            ->expects($this->once())
            ->method('createAnonymousMember')
            ->with($email, $siteId)
            ->willReturn($user);

        // Act
        $result = $this->guestUserService->createAnonymousMember($email, $siteId);

        // Assert
        $this->assertInstanceOf(Member::class, $result);
        $this->assertEquals(456, $result->id);
        $this->assertTrue($result->anonymous);
    }

    public function test_it_sets_anonymous_flag_true()
    {
        // Arrange
        $email = 'guest@example.com';
        $siteId = 1;

        $user = new Member();
        $user->anonymous = true;

        $this->userRepository
            ->method('createAnonymousMember')
            ->willReturn($user);

        // Act
        $result = $this->guestUserService->createAnonymousMember($email, $siteId);

        // Assert
        $this->assertTrue($result->anonymous);
    }

    public function test_it_returns_user_object_with_correct_defaults()
    {
        // Arrange
        $email = 'guest@example.com';
        $siteId = 1;

        $user = new Member();
        $user->id = 789;
        $user->email = $email;
        $user->site_id = $siteId;
        $user->anonymous = true;
        $user->is_active = true;

        $this->userRepository
            ->method('createAnonymousMember')
            ->willReturn($user);

        // Act
        $result = $this->guestUserService->createAnonymousMember($email, $siteId);

        // Assert
        $this->assertEquals($email, $result->email);
        $this->assertEquals($siteId, $result->site_id);
        $this->assertTrue($result->is_active);
    }

    public function test_it_throws_exception_when_email_already_exists()
    {
        // Arrange
        $email = 'existing@example.com';
        $siteId = 1;

        $this->userRepository
            ->expects($this->once())
            ->method('createAnonymousMember')
            ->will($this->throwException(new \App\Framework\Database\Exceptions\UniqueConstraintViolationException()));

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email already exists');

        // Act
        $this->guestUserService->createAnonymousMember($email, $siteId);
    }

    public function test_it_converts_anonymous_member_to_full_account()
    {
        // Arrange
        $memberId = 123;
        $firstName = 'John';
        $lastName = 'Doe';
        $password = 'secure-password';

        $member = new Member();
        $member->id = $memberId;
        $member->first_name = $firstName;
        $member->last_name = $lastName;
        $member->display_name = 'John Doe';
        $member->anonymous = false;

        $this->userRepository
            ->expects($this->once())
            ->method('convertToFullAccount')
            ->with($memberId, $firstName, $lastName, $password)
            ->willReturn($member);

        // Act
        $result = $this->guestUserService->convertToFullAccount(
            $memberId,
            $firstName,
            $lastName,
            $password
        );

        // Assert
        $this->assertInstanceOf(Member::class, $result);
        $this->assertEquals('John Doe', $result->display_name);
        $this->assertFalse($result->anonymous);
    }

    public function test_it_handles_conversion_without_password()
    {
        // Arrange
        $memberId = 123;
        $firstName = 'Jane';
        $lastName = 'Smith';

        $member = new Member();
        $member->id = $memberId;
        $member->first_name = $firstName;
        $member->last_name = $lastName;
        $member->display_name = 'Jane Smith';
        $member->anonymous = false;

        $this->userRepository
            ->expects($this->once())
            ->method('convertToFullAccount')
            ->with($memberId, $firstName, $lastName, null)
            ->willReturn($member);

        // Act
        $result = $this->guestUserService->convertToFullAccount(
            $memberId,
            $firstName,
            $lastName
        );

        // Assert
        $this->assertInstanceOf(Member::class, $result);
    }

    public function test_it_returns_null_when_conversion_fails()
    {
        // Arrange
        $userId = 999;
        $firstName = 'Invalid';
        $lastName = 'User';

        $this->userRepository
            ->expects($this->once())
            ->method('convertToFullAccount')
            ->willReturn(null);

        // Act
        $result = $this->guestUserService->convertToFullAccount(
            $userId,
            $firstName,
            $lastName
        );

        // Assert
        $this->assertNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = $this->createMock(MemberRepository::class);
        $this->guestUserService = new GuestMemberService($this->userRepository);
    }
}