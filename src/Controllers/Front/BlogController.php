<?php

namespace App\Controllers\Front;

use App\Controllers\BlogService;
use App\Controllers\CommentService;
use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;
use function App\Controllers\redirect;
use function App\Controllers\view;

class BlogController extends Controller
{
    public function __construct(
        private readonly BlogService $blogService,
        private readonly CommentService $commentService
    ) {}

    /**
     * Show a specific blog post
     */
    public function show(Page $page, UrlResolutionResult $result, Request $request)
    {
        // Load additional blog-specific data
        $post = $this->blogService->getPostWithRelations($page);
        $comments = $this->commentService->getApprovedComments($page->id);
        $relatedPosts = $this->blogService->getRelatedPosts($page, 5);

        // Handle comment submission
        if ($request->isMethod('POST') && $request->has('comment')) {
            $this->handleCommentSubmission($request, $page);
            return redirect()->back()->with('success', 'Comment submitted for approval');
        }

        // Track page view
        $this->blogService->incrementViewCount($page);

        // SEO data
        $structuredData = $this->blogService->generateStructuredData($post);

        return view('blog.show', [
            'page' => $page,
            'post' => $post,
            'comments' => $comments,
            'relatedPosts' => $relatedPosts,
            'structuredData' => $structuredData,
            'canonical_url' => $result->canonicalUrl,
            'meta_tags' => $this->buildMetaTags($result, $post),
            'breadcrumbs' => $this->buildBreadcrumbs($post),
        ]);
    }

    private function handleCommentSubmission(Request $request, Page $page): void
    {
        /*$validated = $request->validate([
            'comment' => 'required|string|max:1000',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
        ]);

        $this->commentService->create($page, $validated);*/
    }

    private function buildMetaTags(UrlResolutionResult $result, $post): array
    {
        return [
            ['property' => 'og:type', 'content' => 'article'],
            ['property' => 'og:title', 'content' => $post->title],
            ['property' => 'og:description', 'content' => $post->excerpt],
            ['property' => 'og:image', 'content' => $post->featured_image_url],
            ['property' => 'article:author', 'content' => $post->author->name],
            ['property' => 'article:published_time', 'content' => $post->published_at->toISOString()],
            ['name' => 'twitter:card', 'content' => 'summary_large_image'],
        ];
    }

    private function buildBreadcrumbs($post): array
    {
        $breadcrumbs = [
            ['title' => 'Home', 'url' => route('home')],
            ['title' => 'Blog', 'url' => route('blog.index')],
        ];

        if ($post->category) {
            $breadcrumbs[] = [
                'title' => $post->category->name,
                'url' => route('blog.category', $post->category->slug)
            ];
        }

        $breadcrumbs[] = ['title' => $post->title, 'url' => null];

        return $breadcrumbs;
    }
}