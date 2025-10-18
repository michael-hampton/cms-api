<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Models\Author;
use App\Models\Page;
use App\Services\AuthorService;
use Exception;

class AuthorViewController extends Controller
{
    private AuthorService $authorService;

    public function __construct(AuthorService $authorService)
    {
        $this->authorService = $authorService;
        parent::__construct();
    }

    public function show(string $slug)
    {
        try {
            $author = Author::where('slug', $slug)->first();

            if (!$author) {
                // Return 404 view or redirect
                http_response_code(404);
                include __DIR__ . '/../../views/404.php';
                return;
            }

            // Get current page from query string
            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = 12;

            // Get paginated pages for this author
            $paginationData = Page::whereHas('authors', function($query) use ($author) {
                $query->where('authors.id', $author->id);
            })
                ->where('status', 'published')
                ->with(['author'])
                ->orderBy('published_at', 'desc')
                ->paginate($perPage, $currentPage);

            $pages = $paginationData['data'];
            $pagination = $paginationData['pagination'];

            // Render the author view
            return $this->view('estate/author', ['author' => $author, 'pages' => $pages, 'pagination' => $pagination]);;

        } catch (Exception $e) {
            http_response_code(500);
            echo "An error occurred: " . htmlspecialchars($e->getMessage());
        }
    }
}