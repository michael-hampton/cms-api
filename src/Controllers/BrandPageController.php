<?php

namespace App\Controllers;

use App\Models\Menu;
use App\Models\Page;
use App\Models\Tag;

class BrandPageController extends Controller
{
    public function show(string $slug)
    {
        // check if has corresponding page tag
        $tag = Tag::with(['categories'])->where('slug', $slug)->first();

        if (!$tag) {
            return $this->notFound();
        }

        // Get current page from query string
        $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 12;

        // Get paginated pages for this tag
        $paginationData = Page::whereHas('tags', function ($query) use ($tag) {
            $query->where('tags.id', $tag->id);
        })
            ->where('status', 'Published')
            ->with(['tags', 'authors', 'categories'])
            ->orderBy('published_at', 'desc')
            ->paginate($perPage, $currentPage);

        $pages = $paginationData['data'];
        $pagination = [
            'current_page' => $paginationData['pagination']['current_page'],
            'per_page' => $paginationData['pagination']['per_page'],
            'total' => $paginationData['pagination']['total'],
            'last_page' => $paginationData['pagination']['last_page'],
            'from' => $paginationData['pagination']['from'],
            'to' => $paginationData['pagination']['to']
        ];

        return $this->view('brand.show', [
            'pages' => $pages,
            'tag' => $tag,
            'pagination' => $pagination
        ]);
    }

    private function notFound()
    {
        http_response_code(404);
        return $this->view('estate/404', [
            'menu' => Menu::where('is_active', true)->with(['items'])->first()
        ]);
    }

}