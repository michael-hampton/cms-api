<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\ArticleAccessService;

class ArticlePageController extends Controller
{
    public function __construct(
        private readonly PageRepository       $pageRepository,
        private readonly ArticleAccessService $accessService
    )
    {
        parent::__construct();
    }

    /**
     * GET /articles/{slug}
     */
    public function show(string $slug)
    {
        $page = $this->pageRepository->findBySlug($slug);
        $user = Auth::user();

        // Logic for Epic 4 & 5
        $hasAccess = true;
        if ($page->is_paid) {
            $hasAccess = $user && $this->accessService->canView($page, $user->id, $user->email);
        }

        return $this->view('articles.show', [
            'page' => $page,
            'accessGranted' => $hasAccess,
            // Only send preview if access is denied
            'content' => $hasAccess ? $page->content : $this->generatePreview($page->content)
        ]);
    }

    private function generatePreview(string $content): string
    {
        return substr(strip_tags($content), 0, 300) . '...';
    }

    /**
     * GET /articles/create
     * Renders the blank editor for a new article.
     */
    public function create()
    {
        return $this->view('open-collab.articles.create', [
            'page' => null, // Passing null signals "Create" mode to the view
            'site' => SiteContext::slug()
        ]);
    }

    /**
     * GET /articles/{id}/edit
     * Renders the editor with existing data.
     */
    public function edit(int $id)
    {
        $userId = 1; //todo
        $page = $this->pageRepository->find($id);

        // Security: Ensure the contributor owns this page
        if (!$page || $page->contributor_id !== $userId) {
            return $this->redirect('contributor.dashboard');
        }

        return $this->view('open-collab.articles.edit', [
            'page' => $page,
            'site' => SiteContext::slug()
        ]);
    }

    /**
     * GET /articles
     * Purpose: Show the management list of all articles owned by the contributor.
     */
    public function index()
    {
        $userId = Auth::id();
        $userId = 1; //todo needs login
        $siteId = SiteContext::getId();

        // Fetch articles from the repository
        $articles = $this->pageRepository->getContributorPages($userId, $siteId);

        return $this->view('open-collab.articles.index', [
            'articles' => $articles,
            'site' => SiteContext::slug()
        ]);
    }
}