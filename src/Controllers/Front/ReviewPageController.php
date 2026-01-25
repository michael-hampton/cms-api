<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Page;
use App\Services\Cms\MenuRenderer;

class ReviewPageController extends Controller
{
    public function index()
    {
        // Get current page from query string
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        // Get filter parameters
        $sort = $_GET['sort'] ?? 'latest';
        $categoryFilter = $_GET['category'] ?? '';
        $authorFilter = $_GET['author'] ?? '';

        // Build query
        $query = Page::where('page_type', 'review')
            ->where('site_id', SiteContext::getId())
            ->where('status', 'Published')
            ->with(['authors', 'categories', 'tags']);

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

        return $this->view('reviews', [
            'pages' => $pages,
            'pagination' => $pagination,
            'menu' => $menu,
            'currentSort' => $sort,
            'menuRenderer' => new MenuRenderer()
        ]);
    }
}