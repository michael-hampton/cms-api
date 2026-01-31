<?php

namespace App\Controllers\Cms;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\ResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\Pages\PageRepository;
use App\Resources\PageResource;
use function App\Controllers\response;

class SearchController extends Controller
{
    public function __construct(private PageRepository $pageRepository)
    {
        parent::__construct();
    }

    public function pages(Request $request): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $request->merge(['site_id' => $siteId]);
            $query = $request->get('q', '');
            $limit = min($request->get('limit', 20), 50);

            $pages = $this->pageRepository->quickSearch($query, [
                'limit' => $limit,
                'status' => 'published',
                'site_id' => $siteId,
                'with' => ['authors']
            ]);

            $allPages = $pages->items;

            $collection = new ResourceCollection($allPages, PageResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to search pages'
            ], 500);
        }
    }

    public function categories(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $limit = min($request->get('limit', 20), 50);

            // Assuming Category model exists
            $categories = app('App\Models\Category')::where('name', 'LIKE', "%{$query}%")
                ->orWhere('slug', 'LIKE', "%{$query}%")
                ->where('is_active', true)
                ->select('id', 'name', 'slug', 'description', 'image')
                ->limit($limit)
                ->get();

            return $this->jsonResponse([
                'success' => true,
                'data' => $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'title' => $category->name,
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'image' => $category->image,
                        'type' => 'category'
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search categories'
            ], 500);
        }
    }
}