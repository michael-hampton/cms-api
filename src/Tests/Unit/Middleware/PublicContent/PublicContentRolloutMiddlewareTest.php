<?php

namespace App\Tests\Unit\Middleware\PublicContent;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Front\ContentController;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Middleware\PublicContent\PublicContentRolloutMiddleware;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\PublicContentRollout;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentRolloutMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_enabled_content_page_is_rendered_by_api_first_action(): void
    {
        $page = $this->page();
        $expected = Response::html('v2');

        $rollout = Mockery::mock(PublicContentRollout::class);
        $rollout->shouldReceive('enabledFor')->once()->with($page)->andReturnTrue();

        $render = Mockery::mock(RenderPublicContentPageAction::class);
        $render->shouldReceive('execute')->once()->with($page)->andReturn($expected);

        $middleware = new PublicContentRolloutMiddleware(
            $rollout,
            Mockery::mock(PublicContentPageRepository::class),
            $render,
        );

        $response = $middleware->handle(
            $this->request($page, ContentController::class),
            static fn(): Response => Response::html('legacy'),
        );

        self::assertSame($expected, $response);
    }

    public function test_disabled_content_page_falls_through_to_legacy_controller(): void
    {
        $page = $this->page();
        $expected = Response::html('legacy');

        $rollout = Mockery::mock(PublicContentRollout::class);
        $rollout->shouldReceive('enabledFor')->once()->with($page)->andReturnFalse();

        $render = Mockery::mock(RenderPublicContentPageAction::class);
        $render->shouldNotReceive('execute');

        $middleware = new PublicContentRolloutMiddleware(
            $rollout,
            Mockery::mock(PublicContentPageRepository::class),
            $render,
        );

        $response = $middleware->handle(
            $this->request($page, ContentController::class),
            static fn(): Response => $expected,
        );

        self::assertSame($expected, $response);
    }

    public function test_custom_handler_page_is_not_replaced(): void
    {
        $page = $this->page(customHandler: 'App\\Controllers\\Front\\CustomController');
        $expected = Response::html('custom');

        $rollout = Mockery::mock(PublicContentRollout::class);
        $rollout->shouldNotReceive('enabledFor');

        $render = Mockery::mock(RenderPublicContentPageAction::class);
        $render->shouldNotReceive('execute');

        $middleware = new PublicContentRolloutMiddleware(
            $rollout,
            Mockery::mock(PublicContentPageRepository::class),
            $render,
        );

        $response = $middleware->handle(
            $this->request($page, ContentController::class),
            static fn(): Response => $expected,
        );

        self::assertSame($expected, $response);
    }

    public function test_non_content_controller_is_not_replaced(): void
    {
        $page = $this->page();
        $expected = Response::html('other');

        $rollout = Mockery::mock(PublicContentRollout::class);
        $rollout->shouldNotReceive('enabledFor');

        $render = Mockery::mock(RenderPublicContentPageAction::class);
        $render->shouldNotReceive('execute');

        $middleware = new PublicContentRolloutMiddleware(
            $rollout,
            Mockery::mock(PublicContentPageRepository::class),
            $render,
        );

        $response = $middleware->handle(
            $this->request($page, 'App\\Controllers\\Front\\OtherController'),
            static fn(): Response => $expected,
        );

        self::assertSame($expected, $response);
    }

    private function request(Page $page, string $controllerAction): Request
    {
        $request = new Request();
        $request->setAttribute('page', $page);
        $request->setAttribute('controller_action', $controllerAction);

        return $request;
    }

    private function page(?string $customHandler = null): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->custom_handler = $customHandler;

        return $page;
    }
}
