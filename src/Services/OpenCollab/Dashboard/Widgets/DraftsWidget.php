<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

/**
 * Drafts widget — contributor's unpublished articles.
 */
final class DraftsWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {}

    public function key(): string
    {
        return 'drafts';
    }

    public function title(): string
    {
        return 'Your Articles';
    }

    public function visibleFor(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        $siteId = SiteContext::getId();
        $pages  = $this->pageRepository->getContributorPages($user->id, $siteId);

        $published = 0;
        $drafts    = 0;

        foreach ($pages as $page) {
            if ($page->status === 'published') {
                $published++;
            } elseif ($page->status === 'draft') {
                $drafts++;
            }
        }

        return [
            'articles'        => $pages,
            'published_count' => $published,
            'draft_count'     => $drafts,
        ];
    }
}