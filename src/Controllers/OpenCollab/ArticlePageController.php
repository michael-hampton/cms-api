<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePagePermissions;
use App\Controllers\OpenCollab\Concerns\ResolvesUiComponents;
use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\ReadabilityService;

class ArticlePageController extends Controller
{
    use ResolvesUiComponents;
    use AuthorizesSitePagePermissions;

    public function __construct(
        private readonly PageRepository                 $pageRepository,
        private readonly ArticleAccessService           $accessService,
        private readonly ReadabilityService             $readabilityService,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function show(string $slug)
    {
        $page = $this->pageRepository->findBySlug($slug);

        if (!$page || $page->status !== 'published') {
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

        return $this->view('open-collab.articles.show', [
            'page' => $page,
            'accessGranted' => $accessGranted,
            'showPaymentButton' => !$accessGranted && $page->isSellable(),
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
            'extraHead' => '<script src="/js/open-collab/article-approval-editor.js" defer></script>',
        ]);
    }

    public function edit(int $id)
    {
        if ($response = $this->authorizeSitePagePermissions(['content.edit_own'])) {
            return $response;
        }

        $userId = Auth::id();
        $page = $this->pageRepository->getCompletePageData($id);

        if (!$page || (int) $page->contributor_id !== (int) $userId) {
            return $this->redirect('/articles');
        }

        $this->hydrateBlocksForEditor($page);

        $score = $this->readabilityService->getScore($id);

        return $this->view('open-collab.articles.editor', [
            'page' => $page,
            'site' => SiteContext::slug(),
            'siteId' => SiteContext::getId(),
            'readabilityScore' => $score?->readability_score,
            'currentUser' => Auth::user(),
            'extraHead' => '<script src="/js/open-collab/article-approval-editor.js" defer></script>',
        ]);
    }

    private function hydrateBlocksForEditor(Page $page): void
    {
        $defaultIds = [
            'heading' => '__default_heading__',
            'text' => '__default_text__',
            'image' => '__default_image__',
        ];
        $assignedDefaults = [];

        foreach ($page->blocks ?? [] as $block) {
            $data = is_array($block->data) ? $block->data : [];

            foreach ($data as $key => $value) {
                $block->{$key} = $value;
            }

            if (!isset($assignedDefaults[$block->type]) && isset($defaultIds[$block->type])) {
                $block->id = $defaultIds[$block->type];
                $assignedDefaults[$block->type] = true;
            }

            if ($block->type === 'heading' && empty($block->text)) {
                $block->text = (string) ($page->title ?? '');
            }

            if ($block->type === 'text') {
                $paragraphs = $data['paragraphs'] ?? [];
                $block->content = $data['content'] ?? $this->paragraphsToEditorHtml($paragraphs);
            }

            if ($block->type === 'image') {
                $block->cms_image_id = $data['image_id']
                    ?? $data['cms_image_id']
                    ?? null;
                $block->image_url = $data['src']
                    ?? $data['image_url']
                    ?? '';
                $block->thumbnail_url = $data['thumbnail_url']
                    ?? $block->image_url;
            }
        }
    }

    private function paragraphsToEditorHtml(array $paragraphs): string
    {
        return implode('', array_map(static function ($paragraph): string {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                return '';
            }

            return str_starts_with($paragraph, '<')
                ? $paragraph
                : '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
        }, $paragraphs));
    }

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
