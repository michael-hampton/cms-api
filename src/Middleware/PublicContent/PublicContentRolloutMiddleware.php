<?php

namespace App\Middleware\PublicContent;

use App\Actions\PublicContent\RenderPublicContentPageAction;
use App\Framework\Http\Request;
use App\Framework\Middleware\MiddlewareInterface;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\PublicContentRollout;
use Closure;

final class PublicContentRolloutMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PublicContentRollout $rollout,
        private readonly PublicContentPageRepository $pages,
        private readonly RenderPublicContentPageAction $render,
    ) {
    }

    public function handle(Request $request, Closure|callable $next)
    {
        $page = $this->resolvePage($request);

        if (!$page || !$this->rollout->enabledFor($page)) {
            return $next($request);
        }

        return $this->render->execute($page);
    }

    private function resolvePage(Request $request): ?Page
    {
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

        return $this->pages->findHomepage(SiteContext::get());
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
