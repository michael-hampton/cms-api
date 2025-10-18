<?php

namespace App\Controllers;

use App\Models\Menu;
use App\Models\Page;
use App\Models\PageTag;
use App\Models\Tag;
use App\Repositories\TagRepository;
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

            // Get paginated pages for this tag
            $paginationData = Page::whereHas('tags', function($query) use ($tag) {
                $query->where('tags.id', $tag->id);
            })
                ->where('status', 'published')
                ->with(['author'])
                ->orderBy('published_at', 'desc')
                ->paginate($perPage, $currentPage);

            $pages = $paginationData['data'];
            $pagination = $paginationData['pagination'];

            // Render the tag view
            return $this->view('estate/tag', ['pages' => $pages, 'tag' => $tag, 'pagination' => $pagination]);;;

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