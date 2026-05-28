<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\ResolvesUiComponents;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\ArticleApprovalService;

/**
 * Renders admin HTML views for article moderation.
 *
 * Routes:
 *   GET /admin/articles/pending   — approval queue
 */
class AdminArticlePageController extends Controller
{
    use ResolvesUiComponents;

    public function __construct(
        private readonly ArticleApprovalService $approvalService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /admin/articles/pending
     */
    public function pending()
    {
        $articles = $this->approvalService->pendingReviewForSite(SiteContext::getId());

        return $this->view('open-collab.admin.articles.pending', [
            'articles' => $articles,
            'allowedComponentKeys' => $this->allowedUiComponentKeysForSurface('articles.pending'),
            'pageTitle' => 'Approval Queue',
            'activeNav' => 'articles',
            'pendingCount' => $articles->count(),
            'breadcrumbs' => [['label' => 'Approval Queue']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }
}
