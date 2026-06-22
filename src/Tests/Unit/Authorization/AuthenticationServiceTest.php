<?php

namespace App\Tests\Unit\Authorization;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\EloquentTokenRepository;
use App\Framework\Authorization\Exceptions\InactiveUserException;
use App\Framework\Authorization\LoginRequest;
use App\Framework\Authorization\PersonalAccessToken;
use App\Framework\Authorization\SecureTokenGenerator;
use App\Models\Member;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\Members\MemberRepository;
use DateTime;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthenticationServiceTest extends TestCase
{
    private UserRepositoryInterface $users;
    private EloquentTokenRepository $tokens;
    private SecureTokenGenerator $generator;
    private MemberRepository $members;
    private AuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->tokens = Mockery::mock(EloquentTokenRepository::class);
        $this->generator = Mockery::mock(SecureTokenGenerator::class);
        $this->members = Mockery::mock(MemberRepository::class);
        $this->service = new AuthenticationService(
            $this->users,
            $this->tokens,
            $this->generator,
            $this->members,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_issues_expiring_token_with_default_abilities(): void
    {
        $user = $this->user(['id' => 10, 'email' => 'user@example.com']);

        $this->users->shouldReceive('findByEmail')->with('user@example.com', null)->once()->andReturn($user);
        $this->tokens->shouldReceive('revokeUserTokens')->with(10, 5)->once();
        $this->generator->shouldReceive('generate')->once()->andReturn('plain-token');
        $this->tokens->shouldReceive('create')
            ->with(Mockery::on(function (PersonalAccessToken $token): bool {
                return $token->getToken() === 'plain-token'
                    && $token->getTokenableId() === 10
                    && $token->getSiteId() === 5
                    && $token->getAbilities() === ['*']
                    && $token->getExpiresAt() instanceof DateTime;
            }))
            ->once()
            ->andReturnUsing(fn (PersonalAccessToken $token) => $token);

        $response = $this->service->login(new LoginRequest('user@example.com', 'password', 5));

        $this->assertSame('plain-token', $response->accessToken);
    }

    public function test_login_can_issue_open_collab_scoped_token(): void
    {
        $user = $this->user(['id' => 11, 'email' => 'contributor@example.com']);

        $this->users->shouldReceive('findByEmail')->once()->andReturn($user);
        $this->tokens->shouldReceive('revokeUserTokens')->with(11, 5)->once();
        $this->generator->shouldReceive('generate')->once()->andReturn('oc-token');
        $this->tokens->shouldReceive('create')
            ->with(Mockery::on(fn (PersonalAccessToken $token): bool =>
                $token->getAbilities() === [AuthenticationService::ABILITY_OPEN_COLLAB]
            ))
            ->once()
            ->andReturnUsing(fn (PersonalAccessToken $token) => $token);

        $response = $this->service->login(new LoginRequest(
            'contributor@example.com',
            'password',
            5,
            [AuthenticationService::ABILITY_OPEN_COLLAB],
        ));

        $this->assertSame('oc-token', $response->accessToken);
    }

    public function test_validate_access_token_rejects_inactive_user(): void
    {
        $token = new PersonalAccessToken(User::class, 12, 5, 'auth_token', 'plain-token', ['*']);
        $inactiveUser = $this->user(['id' => 12, 'is_active' => false]);

        $this->tokens->shouldReceive('findByToken')->with('plain-token', 5)->once()->andReturn($token);
        $this->users->shouldReceive('findById')->with(12, 5)->once()->andReturn($inactiveUser);
        $this->tokens->shouldReceive('updateLastUsed')->never();

        $this->assertNull($this->service->validateAccessToken('plain-token', 5));
    }

    public function test_validate_access_token_rejects_expired_token(): void
    {
        $token = new PersonalAccessToken(
            User::class,
            12,
            5,
            'auth_token',
            'expired-token',
            ['*'],
            new DateTime('-1 minute'),
        );

        $this->tokens->shouldReceive('findByToken')->with('expired-token', 5)->once()->andReturn($token);
        $this->users->shouldReceive('findById')->never();
        $this->tokens->shouldReceive('updateLastUsed')->never();

        $this->assertNull($this->service->validateAccessToken('expired-token', 5));
    }

    public function test_validate_access_token_keeps_legacy_non_expiring_tokens_valid(): void
    {
        $token = new PersonalAccessToken(User::class, 13, 5, 'auth_token', 'legacy-token', ['*'], null, 99);
        $activeUser = $this->user(['id' => 13]);

        $this->tokens->shouldReceive('findByToken')->with('legacy-token', 5)->once()->andReturn($token);
        $this->users->shouldReceive('findById')->with(13, 5)->once()->andReturn($activeUser);
        $this->tokens->shouldReceive('updateLastUsed')->with(99)->once();

        $this->assertSame($token, $this->service->validateAccessToken('legacy-token', 5));
    }

    public function test_create_token_refuses_inactive_user(): void
    {
        $this->expectException(InactiveUserException::class);

        $this->service->createToken($this->user(['is_active' => false]), 5);
    }

    public function test_cross_site_member_validation_accepts_active_member_token(): void
    {
        $token = new PersonalAccessToken(Member::class, 22, 7, 'auth_token', 'member-token', ['*'], null, 101);
        $member = Mockery::mock(Member::class)->makePartial();
        $member->setAttribute('id', 22);
        $member->setAttribute('status', 'active');
        $member->setAttribute('is_active', true);

        $this->tokens->shouldReceive('findMemberTokenAcrossSites')
            ->with('member-token')
            ->once()
            ->andReturn($token);
        $this->members->shouldReceive('find')->with(22)->once()->andReturn($member);
        $this->tokens->shouldReceive('updateLastUsed')->with(101)->once();

        $this->assertSame($token, $this->service->validateMemberAccessTokenAcrossSites('member-token'));
    }

    public function test_cross_site_member_validation_rejects_user_token(): void
    {
        $token = new PersonalAccessToken(User::class, 22, 7, 'auth_token', 'user-token', ['*'], null, 102);

        $this->tokens->shouldReceive('findMemberTokenAcrossSites')
            ->with('user-token')
            ->once()
            ->andReturn($token);
        $this->members->shouldReceive('find')->never();
        $this->tokens->shouldReceive('updateLastUsed')->never();

        $this->assertNull($this->service->validateMemberAccessTokenAcrossSites('user-token'));
    }

    public function test_cross_site_member_validation_rejects_expired_token(): void
    {
        $token = new PersonalAccessToken(
            Member::class,
            22,
            7,
            'auth_token',
            'expired-member-token',
            ['*'],
            new DateTime('-1 minute'),
            103,
        );

        $this->tokens->shouldReceive('findMemberTokenAcrossSites')
            ->with('expired-member-token')
            ->once()
            ->andReturn($token);
        $this->members->shouldReceive('find')->never();
        $this->tokens->shouldReceive('updateLastUsed')->never();

        $this->assertNull($this->service->validateMemberAccessTokenAcrossSites('expired-member-token'));
    }

    public function test_cross_site_member_validation_rejects_inactive_member(): void
    {
        $token = new PersonalAccessToken(Member::class, 22, 7, 'auth_token', 'inactive-token', ['*'], null, 104);
        $member = Mockery::mock(Member::class)->makePartial();
        $member->setAttribute('id', 22);
        $member->setAttribute('is_active', false);

        $this->tokens->shouldReceive('findMemberTokenAcrossSites')
            ->with('inactive-token')
            ->once()
            ->andReturn($token);
        $this->members->shouldReceive('find')->with(22)->once()->andReturn($member);
        $this->tokens->shouldReceive('updateLastUsed')->never();

        $this->assertNull($this->service->validateMemberAccessTokenAcrossSites('inactive-token'));
    }

    private function user(array $attributes = []): User
    {
        $password = $attributes['password'] ?? password_hash('password', PASSWORD_DEFAULT);

        /** @var User $user */
        $user = Mockery::mock(User::class)->makePartial();
        foreach (array_merge([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => $password,
            'role' => 'user',
            'is_active' => true,
        ], $attributes) as $key => $value) {
            $user->setAttribute($key, $value);
        }

        return $user;
    }
}
