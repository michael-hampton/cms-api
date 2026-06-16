<?php

namespace App\Middleware\PublicContent;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Controllers\Front\ContentController;
use App\Framework\Http\Request;
use App\Framework\Middleware\MiddlewareInterface;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Repositories\PublicContent\PublicTerritoryRepository;
use App\Services\PublicContent\PublicContentRollout;
use Closure;

final class PublicContentRolloutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicContentPageRepository $pages,
        private readonly PublicTerritoryRepository $territories,
        private readonly RenderPublicContentPageAction $render,
    ) {
    }

    public function handle(Request $request, Closure|callable $next)
    {
        if (!$this->targetsLegacyContentController($request)) {
            return $next($request);
        }

        $page = $this->resolvePage($request);

        if (
            !$page
            || $page->custom_handler
            || !$this->rollout->enabledFor($page)
        ) {
            return $next($request);
        }

        $territory = $this->territories->findActiveForPage(
            SiteContext::getId(),
            (int) $page->id,
        );

        return $this->render->execute($page, false, $territory);
    }

    private function targetsLegacyContentController(Request $request): bool
    {
        $controllerAction = (string)$request->getAttribute('controller_action', '');

        if ($controllerAction === '') {
            return false;
        }

        $controllerClass = str_contains($controllerAction, '@')
            ? explode('@', $controllerAction, 2)[0]
            : $controllerAction;

        return $controllerClass === ContentController::class;
    }

    private function resolvePage(Request $request): ?Page
    {
        $attributePage = $request->getAttribute('page');
        if ($attributePage instanceof Page) {
            return $attributePage;
        }

        $routePage = $request->route('page');
        if ($routePage instanceof Page) {
            return $routePage;
        }

        $slug = $this->resolveSlug($request);
        if ($slug !== null && $slug !== '') {
            return $this->pages->findPublishedBySlug(
                SiteContext::getId(),
                $slug,
            );
        }

        return null;
    }

    private function resolveSlug(Request $request): ?string
    {
        foreach (['page', 'slug', 'pageSlug'] as $key) {
            $value = $request->route($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
