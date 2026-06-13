<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Controllers\OpenCollab\Concerns\ResolvesUiComponents;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\ReadabilityService;

/**
 * Renders the public and contributor-facing article pages.
 *
 * Payment modal visibility rule:
 *   The modal is shown ONLY when the page is sellable (isSellable() === true).
 *   This requires: visibility = premium, price > 0, premium_approved_at set,
 *   and monetisation_disabled_at = null.
 *   The backend never relies on frontend modal state for eligibility.
 */
class ArticlePageController extends Controller
{
    use ResolvesUiComponents;
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly PageRepository                 $pageRepository,
        private readonly ArticleAccessService           $accessService,
        private readonly ReadabilityService             $readabilityService,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
        parent::__construct();
    }

    /**
     * GET /articles/{slug}
     *
     * Three rendering states:
     *   Free article                → full content, no paywall
     *   Approved premium + no access → preview + payment button
     *   Premium but NOT approved    → locked-content message, NO payment button
     *   Approved premium + access   → full content
     */
    public function show(string $slug)
    {
        $page = $this->pageRepository->findBySlug($slug);

        if (!$page) {
            return $this->notFound();
        }

        if ($page->status !== 'published') {
            return $this->notFound();
        }

        $user = Auth::user();
        $userId = $user?->id;
        $email = $user?->email;

        $accessGranted = $this->accessService->canView($page, $userId, $email);

        $authorName = $page->contributor_id
            ? User::find($page->contributor_id)?->name
            : null;

        $content = $accessGranted
            ? $page->content
            : $this->previewContent($page->content);

        // Payment button is shown only for pages that are fully sellable.
        // An unapproved or disabled premium page shows the locked message
        // but not the payment button — users cannot purchase it.
        $showPaymentButton = !$accessGranted && $page->isSellable();

        return $this->view('open-collab.articles.show', [
            'page' => $page,
            'accessGranted' => $accessGranted,
            'showPaymentButton' => $showPaymentButton,
            'content' => $content,
            'authorName' => $authorName,
            'readerEmail' => $email ?? '',
            'site' => SiteContext::slug(),
            'stripePublicKey' => config('stripe.public_key', ''),
        ]);
    }

    public function queue()
    {
        return $this->view('open-collab.admin.articles.queue', [
            'site' => SiteContext::slug(),
        ]);
    }

    private function previewContent(string $content): string
    {
        $plain = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mb_substr($plain, 0, 300) . '…';
    }

    /**
     * GET /articles/create
     */
    public function create()
    {
        if ($response = $this->authorizeSitePagePermissions(['content.create'])) {
            return $response;
        }

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
        if ($response = $this->authorizeSitePagePermissions(['content.edit_own'])) {
            return $response;
        }

        $userId = Auth::id();
        $page = $this->pageRepository->find($id);

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

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * GET /articles
     */
    public function index()
    {
        $articles = $this->pageRepository->getContributorPages(
            Auth::id(),
            SiteContext::getId(),
        );

        return $this->view('open-collab.articles.index', [
            'articles' => $articles,
            'allowedComponentKeys' => $this->allowedUiComponentKeysForSurface('articles.index'),
            'site' => SiteContext::slug(),
            'currentUser' => Auth::user(),
        ]);
    }
}