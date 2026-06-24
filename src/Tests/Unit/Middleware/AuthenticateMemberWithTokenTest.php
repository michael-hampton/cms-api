<?php

namespace App\Tests\Unit\Middleware;

use App\Framework\Authorization\AuthenticatedMember;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Middleware\AuthenticateMemberWithToken;
use Mockery;
use PHPUnit\Framework\TestCase;

final class AuthenticateMemberWithTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        MemberAuth::setMember(null);
        $_COOKIE = [];
        $_SERVER = [];
        Mockery::close();

        parent::tearDown();
    }

    public function test_press_stack_account_page_without_token_renders_guest_flow_even_when_member_session_exists(): void
    {
        MemberAuth::setMember(new AuthenticatedMember(
            123,
            'member@example.com',
            'Test',
            'Member',
            'Test Member',
            ['basic'],
        ));

        $authService = Mockery::mock(AuthenticationService::class);
        $authService->shouldReceive('validateAccessToken')->never();

        $middleware = new AuthenticateMemberWithToken($authService);
        $called = false;

        $response = $middleware->handle(
            $this->request('/press-stack/account/subscriptions'),
            function () use (&$called) {
                $called = true;

                self::assertFalse(MemberAuth::check());

                return Response::make('guest account modal');
            },
        );

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
    }

    private function request(string $path): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $path;

        $request = new Request();
        $request->setPath($path);

        return $request;
    }
}
