<?php

namespace App\Tests\Unit\Authorization;

use App\Framework\Authorization\AuthenticateWithToken;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\PersonalAccessToken;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthenticateWithTokenTest extends TestCase
{
    private AuthenticationService $auth;
    private UserRepositoryInterface $users;
    private AuthenticateWithToken $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [];
        $_SESSION = [];
        $this->auth = Mockery::mock(AuthenticationService::class);
        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->middleware = new AuthenticateWithToken($this->auth, $this->users);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $_SERVER = [];
        $_SESSION = [];
        parent::tearDown();
    }

    public function test_missing_bearer_token_returns_401(): void
    {
        $response = $this->middleware->handle($this->request('/api/test-site/auth/me'), fn () => null);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_invalid_bearer_token_returns_401(): void
    {
        $this->auth->shouldReceive('validateAccessToken')->with('bad-token', 9)->once()->andReturn(null);
        $this->auth->shouldReceive('validateUserAccessTokenAcrossSites')->with('bad-token')->once()->andReturn(null);

        $response = $this->middleware->handle(
            $this->request('/api/test-site/auth/me', 'bad-token', 9),
            fn () => null,
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_open_collab_request_rejects_unrelated_token_scope(): void
    {
        $token = new PersonalAccessToken(User::class, 20, 1, 'auth_token', 'plain-token', ['billing']);

        $this->auth->shouldReceive('validateAccessToken')->with('plain-token', 2)->once()->andReturn($token);
        $this->users->shouldReceive('findById')->never();

        $response = $this->middleware->handle(
            $this->request('/api/other-site/open-collab/dashboard', 'plain-token', 2),
            fn () => null,
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_token_for_user_without_site_access_returns_403(): void
    {
        $token = new PersonalAccessToken(User::class, 21, 1, 'auth_token', 'plain-token', ['*'], null, 5);
        $user = $this->user(['id' => 21]);

        $this->auth->shouldReceive('validateAccessToken')->with('plain-token', 2)->once()->andReturn($token);
        $this->users->shouldReceive('findById')->with(21, 2)->once()->andReturn($user);

        $response = $this->middleware->handle(
            $this->request('/api/other-site/open-collab/dashboard', 'plain-token', 2),
            fn () => \App\Framework\Http\Response::json(['ok' => true]),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNull(Session::get('user_id'));
    }

    private function request(string $path, ?string $token = null, int $siteId = 1): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_X_SITE_ID'] = (string) $siteId;

        if ($token !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $request = new Request();
        $request->setPath($path);

        return $request;
    }

    private function user(array $attributes = []): User
    {
        /** @var User $user */
        $user = Mockery::mock(User::class)->makePartial();
        foreach (array_merge([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'user@example.com',
            'role' => 'contributor',
            'is_active' => true,
        ], $attributes) as $key => $value) {
            $user->setAttribute($key, $value);
        }

        return $user;
    }
}
