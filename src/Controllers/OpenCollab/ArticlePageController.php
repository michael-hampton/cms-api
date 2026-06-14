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
            'extraHead' => $this->approvalEditorScript(null, null),
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
            'extraHead' => $this->approvalEditorScript((int)$page->id, (string)$page->status),
        ]);
    }

    private function approvalEditorScript(?int $pageId, ?string $status): string
    {
        $pageIdJson = json_encode($pageId, JSON_THROW_ON_ERROR);
        $statusJson = json_encode($status, JSON_THROW_ON_ERROR);

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', () => {
    const approvalButton = document.getElementById('publish-btn');
    if (!approvalButton) return;

    const pageId = {$pageIdJson};
    const pageStatus = {$statusJson};
    const approvalIcon = approvalButton.querySelector('svg')?.outerHTML ?? '';

    const setButton = (label, disabled = false) => {
        approvalButton.innerHTML = `${approvalIcon} ${label}`;
        approvalButton.disabled = disabled;
    };

    if (pageStatus === 'waiting_approval') {
        approvalButton.removeAttribute('onclick');
        setButton('Approval requested', true);
        return;
    }

    approvalButton.removeAttribute('onclick');
    setButton('Request approval');

    const showApprovalError = (message) => {
        const errorBox = document.getElementById('editor-errors');
        if (errorBox) {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        }
        if (typeof showToast === 'function') {
            showToast(message, false);
        }
    };

    const submitForApproval = async (id) => {
        const endpoint = `/api/${SITE}/open-collab/pages/${id}/submit`;
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
            },
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const errors = data.errors ? Object.values(data.errors).flat().join(' ') : null;
            throw new Error(errors || data.message || data.error || 'Could not request approval.');
        }

        sessionStorage.removeItem('oc_request_approval_after_save');
        if (typeof showToast === 'function') {
            showToast('✓ Approval requested');
        }
        setButton('Approval requested', true);
    };

    approvalButton.addEventListener('click', async () => {
        setButton('Requesting approval…', true);

        try {
            if (!pageId) {
                sessionStorage.setItem('oc_request_approval_after_save', '1');
                const saved = await persistArticle('draft', {silent: true});
                if (!saved) {
                    sessionStorage.removeItem('oc_request_approval_after_save');
                    setButton('Request approval');
                }
                return;
            }

            const saved = await persistArticle('draft', {silent: true});
            if (!saved) {
                setButton('Request approval');
                return;
            }

            await submitForApproval(pageId);
        } catch (error) {
            sessionStorage.removeItem('oc_request_approval_after_save');
            setButton('Request approval');
            showApprovalError(error instanceof Error ? error.message : 'Could not request approval.');
        }
    });

    if (pageId && sessionStorage.getItem('oc_request_approval_after_save') === '1') {
        setButton('Requesting approval…', true);
        submitForApproval(pageId).catch((error) => {
            sessionStorage.removeItem('oc_request_approval_after_save');
            setButton('Request approval');
            showApprovalError(error instanceof Error ? error.message : 'Could not request approval.');
        });
    }
});
</script>
HTML;
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
