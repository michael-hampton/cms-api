<?php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\PageRepository;

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
            ]);

            $allPages = $pages->items;
            $formattedPages = [];

            foreach ($allPages as $page) {
                $page['crop_overrides'] = json_decode($page['crop_overrides'], true) ?? [];
                $page['resolved_images'] = json_decode($page['resolved_images'], true) ?? [];

                $formattedPages[] = $page;
            }

            return $this->jsonResponse($formattedPages);
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