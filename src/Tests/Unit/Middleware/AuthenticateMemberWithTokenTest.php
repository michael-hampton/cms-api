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

    public function test_press_stack_account_page_without_member_session_renders_guest_flow_even_when_token_cookie_exists(): void
    {
        $_COOKIE['member_access_token'] = 'remembered-token';

        $authService = Mockery::mock(AuthenticationService::class);
        $authService->shouldReceive('validateAccessToken')->never();
        $authService->shouldReceive('validateMemberAccessTokenAcrossSites')->never();

        $middleware = new AuthenticateMemberWithToken($authService);
        $called = false;

        $response = $middleware->handle(
            $this->request('/press-stack/account/subscriptions'),
            function () use (&$called) {
                $called = true;

                self::assertFalse(MemberAuth::check());
                self::assertArrayNotHasKey('member_access_token', $_COOKIE);

                return Response::html('guest account modal');
            },
        );

        self::assertTrue($called);
        self::assertSame(200, $response->getStatusCode());
    }

    public function test_press_stack_account_page_with_member_session_can_continue_to_token_validation(): void
    {
        $_COOKIE['member_access_token'] = 'remembered-token';
        MemberAuth::setMember(new AuthenticatedMember(
            123,
            'member@example.com',
            'Test',
            'Member',
            'Test Member',
            ['basic'],
        ));

        $authService = Mockery::mock(AuthenticationService::class);
        $authService->shouldReceive('validateMemberAccessTokenAcrossSites')->with('remembered-token')->once()->andReturn(null);

        $middleware = new AuthenticateMemberWithToken($authService);

        $response = $middleware->handle(
            $this->request('/press-stack/account/subscriptions'),
            fn () => Response::html('account page'),
        );

        self::assertSame(302, $response->getStatusCode());
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
