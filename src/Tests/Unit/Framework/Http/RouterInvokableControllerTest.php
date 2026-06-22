<?php

namespace App\Tests\Unit\Framework\Http;

use App\Framework\Container;
use App\Framework\Http\Request;
use App\Framework\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterInvokableControllerTest extends TestCase
{
    public function test_invokable_controller_receives_route_parameters(): void
    {
        $container = new Container();
        $container->instance(InvokableRouteController::class, new InvokableRouteController());

        $router = new TestRouter($container);
        $request = new Request();

        $result = $router->callRouteAction(
            InvokableRouteController::class,
            $request,
            ['id' => '42'],
        );

        self::assertSame(42, $result['id']);
        self::assertSame($request, $result['request']);
    }
}

final class TestRouter extends Router
{
    public function callRouteAction(
        mixed $action,
        Request $request,
        array $routeParams = [],
    ): mixed {
        return $this->callAction($action, $request, $routeParams);
    }
}

final class InvokableRouteController
{
    public function __invoke(int $id, Request $request): array
    {
        return [
            'id' => $id,
            'request' => $request,
        ];
    }
}
