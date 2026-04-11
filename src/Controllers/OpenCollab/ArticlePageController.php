<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\ReadabilityService;

/**
 * Renders the public and contributor-facing article pages.
 *
 * All `$userId = 1; //todo` stubs have been replaced with Auth::id().
 * Auth is resolved via the token (API calls) or session (web); both
 * paths are handled by Auth::user() already.
 *
 * Draft-first rule: ContributorPageService sets status = draft at creation.
 * This controller does not override that; it just reads what's stored.
 */
class ArticlePageController extends Controller
{
    public function __construct(
        private readonly PageRepository       $pageRepository,
        private readonly ArticleAccessService $accessService,
        private readonly ReadabilityService   $readabilityService,
    )
    {
        parent::__construct();
    }

    /**
     * GET /articles/{slug}
     * Public article page. Three rendering states:
     *   Free article           → full content
     *   Paid + no access       → preview + paywall
     *   Paid + access granted  → full content
     */
    public function show(string $slug)
    {
        $page = $this->pageRepository->findBySlug($slug);

        if (!$page) {
            return $this->notFound();
        }

        // Only published articles are publicly accessible
        if ($page->status !== 'published') {
            return $this->notFound();
        }

        $user = Auth::user();
        $userId = $user?->id;
        $email = $user?->email;

        $accessGranted = $this->accessService->canView($page, $userId, $email);

        $authorName = $page->contributor_id
            ? \App\Models\User::find($page->contributor_id)?->name
            : null;

        $content = $accessGranted
            ? $page->content
            : $this->previewContent($page->content);

        return $this->view('open-collab.articles.show', [
            'page' => $page,
            'accessGranted' => $accessGranted,
            'content' => $content,
            'authorName' => $authorName,
            'readerEmail' => $email ?? '',
            'site' => SiteContext::slug(),
            'stripePublicKey' => config('stripe.public_key', ''),
        ]);
    }

    /**
     * GET /articles/create
     */
    public function create()
    {
        $this->requireAuth();

        return $this->view('open-collab.articles.editor', [
            'page' => null,
            'site' => SiteContext::slug(),
            'siteId' => SiteContext::getId(),
            'readabilityScore' => null,
            'currentUser' => Auth::user(),
        ]);
    }

    /**
     * GET /articles/{id}/edit
     */
    public function edit(int $id)
    {
        $this->requireAuth();

        $userId = Auth::id();
        $page = $this->pageRepository->find($id);

        //dd($page);

        // Ownership check
        if (!$page || (int)$page->contributor_id !== (int)$userId) {
            return $this->redirect('/articles');
        }

        $score = $this->readabilityService->getScore($id);

        return $this->view('open-collab.articles.editor', [
            'page' => $page,
            'site' => SiteContext::slug(),
            'siteId' => SiteContext::getId(),
            'readabilityScore' => $score?->readability_score,
            'currentUser' => Auth::user(),
        ]);
    }

    /**
     * GET /articles
     * Contributor's article management list.
     */
    public function index()
    {
        $this->requireAuth();

        $articles = $this->pageRepository->getContributorPages(
            Auth::id(),
            SiteContext::getId(),
        );

        return $this->view('open-collab.articles.index', [
            'articles' => $articles,
            'site' => SiteContext::slug(),
            'currentUser' => Auth::user(),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Returns the first ~300 characters of the content stripped of HTML.
     * The controller — not the view — is responsible for this truncation.
     */
    private function previewContent(string $content): string
    {
        $plain = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_substr($plain, 0, 300) . '…';
    }

    /**
     * Redirect unauthenticated users to login, preserving intent.
     */
    private function requireAuth(): void
    {
        if (!Auth::check()) {
            $intendedUrl = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: /login?redirect=' . $intendedUrl);
            exit;
        }
    }
}