<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Models\Author;
use App\Models\Menu;
use App\Models\Page;
use App\Repositories\CategoryRepository;
use App\Repositories\TagRepository;
use App\Services\MenuRenderer;
use Exception;

class AuthorViewController extends Controller
{
    public function __construct(private CategoryRepository $categoryRepository, private TagRepository $tagRepository)
    {
        parent::__construct();
    }

    public function show(string $slug)
    {
        try {

            $menu = Menu::where('is_active', true)
                ->where('site_id', SiteContext::getId())
                ->where('menu_type', 'header')
                ->with(['items'])
                ->first();

            $author = Author::where('slug', $slug)->first();

            if (!$author) {
                http_response_code(404);
                include __DIR__ . '/../../views/404.php';
                return;
            }

            // Get current page from query string
            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = 12;

            // Get filter parameters
            $sort = $_GET['sort'] ?? 'latest';
            $categoryFilter = $_GET['category'] ?? '';

            // Build query
            $query = Page::whereHas('authors', function ($query) use ($author) {
                $query->where('authors.id', $author->id);
            })
                ->where('status', 'published')
                ->with(['authors', 'categories', 'tags']);

            // Apply category filter
            if ($categoryFilter) {
                $query->whereHas('categories', function ($q) use ($categoryFilter) {
                    $q->where('categories.id', $categoryFilter);
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

            // Render the author view
            return $this->view('estate/author', [
                'author' => $author,
                'menu' => $menu,
                'pages' => $pages,
                'pagination' => $pagination,
                'currentSort' => $sort,
                'categories' => $this->categoryRepository->getActive(),
                'tags' => $this->tagRepository->all(),
                'menuRenderer' => new MenuRenderer()
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo "An error occurred: " . htmlspecialchars($e->getMessage());
        }
    }
}