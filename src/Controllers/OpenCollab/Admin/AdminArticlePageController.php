<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
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
        $this->requireAdmin();

        $articles = $this->approvalService->pendingReviewForSite(SiteContext::getId());

        return $this->view('open-collab.admin.articles.pending', [
            'articles' => $articles,
            'pageTitle' => 'Approval Queue',
            'activeNav' => 'articles',
            'pendingCount' => $articles->count(),
            'breadcrumbs' => [['label' => 'Approval Queue']],
            'currentUser' => Auth::user(),
            'site' => SiteContext::slug(),
        ]);
    }

    private function requireAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role ?? '', ['admin', 'agent'], true)) {
            header('Location: /login');
            exit;
        }
    }
}