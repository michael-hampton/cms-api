<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Tag;
use App\Repositories\TagRepository;
use App\Services\MenuRenderer;
use Exception;

class TagViewController extends Controller
{
    private TagRepository $tagRepository;

    public function __construct(TagRepository $tagRepository)
    {
        $this->tagRepository = $tagRepository;
        parent::__construct();
    }

    public function show(string $slug)
    {
        try {
            $tag = Tag::where('slug', $slug)->first();

            if (!$tag) {
                return $this->notFound();
            }

            // Get current page from query string
            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = 12;

            // Get filter parameters
            $sort = $_GET['sort'] ?? 'latest';
            $categoryFilter = $_GET['category'] ?? '';
            $authorFilter = $_GET['author'] ?? '';

            // Build query
            $query = Page::with(['authors', 'categories', 'tags'])->whereHas('tags', function ($query) use ($tag) {
                $query->where('tags.id', $tag->id);
            })
                ->where('status', 'published');

            // Apply category filter
            if ($categoryFilter) {
                $query->whereHas('categories', function ($q) use ($categoryFilter) {
                    $q->where('categories.id', $categoryFilter);
                });
            }

            // Apply author filter
            if ($authorFilter) {
                $query->whereHas('authors', function ($q) use ($authorFilter) {
                    $q->where('authors.id', $authorFilter);
                });
            }

            // Apply sorting
            switch ($sort) {
                case 'oldest':
                    $query->orderBy('published_at', 'asc');
                    break;
                case 'title':
                    $query->orderBy('title', 'asc');
                    break;
                case 'latest':
                default:
                    $query->orderBy('published_at', 'desc');
                    break;
            }

            $paginationData = $query->paginate($perPage, $currentPage);

            $pages = $paginationData['data'];
            $pagination = $paginationData['pagination'];

            $menu = Menu::where('is_active', true)
                ->where('site_id', SiteContext::getId())
                ->where('menu_type', 'header')
                ->with(['items'])
                ->first();

            // Render the tag view
            return $this->view('estate/tag', [
                'pages' => $pages,
                'menu' => $menu,
                'tag' => $tag,
                'pagination' => $pagination,
                'currentSort' => $sort,
                'menuRenderer' => new MenuRenderer()
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo "An error occurred: " . htmlspecialchars($e->getMessage());
        }
    }

    private function notFound()
    {
        http_response_code(404);
        return $this->view('estate/404', [
            'menu' => Menu::where('is_active', true)->with(['items'])->first()
        ]);
    }
}