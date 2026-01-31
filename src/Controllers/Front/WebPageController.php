<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Models\Page;
use App\Parsers\BlockRegistry;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\Cms\Pages\BlockParserService;
use App\Services\Cms\Pages\PageService;
use Exception;

class WebPageController extends Controller
{
    public function __construct(
        private PageRepository $pageRepository,
        private PageService $pageService,
        private BlockRegistry $blockRegistry,
        private BlockParserService $blockParserService)
    {
        parent::__construct();
    }

    // todo homepage
    public function index(Request $request): Response
    {
        $page = [
            'title' => 'test mike',
            'main' => [
                'title' => 'test mike',
                'subtitle' => 'test subtitle',
            ],
            'blocks' => [
                // 1. heading
                [
                    "type" => "heading",
                    "title" => "",
                    "text" => "heading text",
                    "subtitle" => "subtitle",
                    "level" => 2,
                    "id" => "f256860d-620d-4352-a1c0-70c25360e4dd"
                ],
                // 2. text
                [
                    "type" => "text",
                    "paragraphs" => ["test abc"],
                    "id" => "1b916a42-b41b-4e35-b96a-aa10103df73e"
                ],
                // 3. list
                [
                    "type" => "list",
                    "isEditing" => false,
                    "listType" => "ul",
                    "schemaType" => "none",
                    "startIndex" => 1,
                    "items" => ["list item 1", "list item 2"],
                    "id" => "aa737aa7-f3be-4eb9-9b33-32e786c62be2"
                ],
                // 4. image
                [
                    "type" => "image",
                    "alt" => "alt text",
                    "caption" => "caption",
                    "name" => "",
                    "layout" => "full",
                    "id" => "73406140-4ef4-411b-a072-205d79002e6f",
                    "src" => "https://via.placeholder.com/600x300",
                    "linkUrl" => "",
                    "noFollow" => false,
                    "sponsored" => false,
                    "openInNewTab" => false
                ],
                // 5. note
                [
                    "type" => "note",
                    "title" => "test a",
                    "paragraphs" => ["test a"],
                    "id" => "43a21e00-ce80-44d7-86f1-4d644862e249",
                    "image" => ""
                ],
                // 6. deal
                [
                    "type" => "deal",
                    "brand" => "brand",
                    "currency" => "$",
                    "description" => "test",
                    "layout" => "",
                    "link" => "http://www.bbc.co.uk",
                    "linkOptions" => "",
                    "price" => 12,
                    "productName" => "product name",
                    "savingMode" => "percent",
                    "title" => "label",
                    "id" => "d3847024-423d-4196-a81e-74ca5c8055df",
                    "noFollow" => false,
                    "sponsored" => false,
                    "openInNewTab" => false,
                    "image" => "",
                    "salePrice" => 10,
                    "showDealButton" => true,
                    "starBlock" => true
                ],
                // 7. product
                [
                    "type" => "product",
                    "brand" => "brand",
                    "cons" => [],
                    "currency" => "$",
                    "displayAs" => "button",
                    "isFeatured" => false,
                    "layout" => "standard",
                    "link" => "http://www.bbc.co.uk",
                    "linkOptions" => "",
                    "linkText" => "Buy Now",
                    "name" => "label",
                    "noFollow" => false,
                    "openInNewTab" => false,
                    "price" => 12,
                    "pros" => [],
                    "retailer" => "",
                    "savingMode" => "",
                    "showReviewPanel" => true,
                    "id" => "d08189ec-ff50-4522-8456-bd47397ef797",
                    "sponsored" => false,
                    "image" => "",
                    "productName" => "product name",
                    "salePrice" => 10,
                    "description" => "test",
                    "review" => [
                        "rating" => 4.5,
                        "reviewPercent" => 90,
                        "pros" => ["abc"],
                        "cons" => ["def"],
                        "articleId" => "",
                        "articleTitle" => "",
                        "articleUrl" => ""
                    ]
                ],
                // 9. gallery
                [
                    "type" => "gallery",
                    "layout" => 'carousel',
                    "slides" => [
                        [
                            "file" => [
                                "url" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA",
                                "name" => "test image 2.jpg",
                                "alt" => "",
                                "caption" => ""
                            ],
                            "alt" => "",
                            "link" => "http://www.bbc.co.uk",
                            "noFollow" => false,
                            "sponsored" => false,
                            "openInNewTab" => false,
                        ],
                    ],
                    "id" => "6b539449-a015-4e8e-beae-1b5536d4b9a8"
                ],
                // 10. schema (question)
                [
                    "type" => "schema",
                    "schemaType" => "question",
                    "id" => "33a2b39a-eff8-4c52-8494-0e70b4d0c7ac",
                    "title" => "",
                    "description" => "",
                    "image" => "",
                    "question" => "question",
                    "answer" => "answer",
                    "expansion" => "test"
                ],
                // 11. schema (how-to)
                [
                    "type" => "schema",
                    "schemaType" => "how-to",
                    "id" => "55ed9390-8844-4d3c-a296-f125a4b1a58f",
                    "title" => "title",
                    "description" => "description",
                    "image" => "",
                    "question" => "",
                    "answer" => "",
                    "expansion" => ""
                ],
                // 12. section
                [
                    "type" => "section",
                    "navigationText" => "nav text",
                    "title" => "section title",
                    "headingType" => "h2",
                    "excludeFromNav" => false,
                    "id" => "907f44b5-b8fd-4cca-ba10-a728b9c94fbe"
                ],
                // 13. quote
                [
                    "type" => "quote",
                    "text" => "quote text",
                    "id" => "6ed6fe23-7965-4071-98ab-7532e588ca4b",
                    "attribution" => "attribution"
                ],
                // 14. buying-guide
                [
                    "type" => "buying-guide",
                    "cons" => [],
                    "displayAs" => "button",
                    "isEditing" => false,
                    "linkText" => "Buy Now",
                    "noFollow" => false,
                    "openInNewTab" => false,
                    "pros" => [],
                    "showReviewPanel" => false,
                    "specs" => [
                        ["text" => "spec1", "value" => "value1"]
                    ],
                    "sponsored" => false,
                    "subtitle" => "strapline",
                    "title" => "buying guide",
                    "url" => "http://www.bbc.com",
                    "id" => "2f909326-9cde-4b72-bf8b-12e22f0bbffb"
                ],
                // 15. award
                [
                    "type" => "award",
                    "subcategory" => "subcategory",
                    "productName" => "product name",
                    "caption" => "caption",
                    "alt" => "alt text",
                    "winner" => false,
                    "rating" => 5,
                    "strapline" => "strapline",
                    "id" => "b6616465-1dcc-4d93-8fb2-5f14e4142241",
                    "image" => "",
                    "reviewPercent" => 100
                ],
                // 16. info
                [
                    "type" => "info",
                    "infoType" => "ingredients",
                    "description" => "test test test",
                    "id" => "68deda51-11e4-484f-9c3f-51d1b27f971a"
                ],
            ]
        ];

        $generatedHtml = [];

        foreach ($page['blocks'] as $count => $block) {
            $generatedHtml[] = $this->blockParserService->buildBlock(1, $block, $count);
        }

        $pages = Page::published()->with(['tags', 'categories', 'seo', 'meta', 'social'])->get(); //todo maybe we can assume each page is a property?

        return $this->view('pages/show', ['page' => 1, 'html' => $generatedHtml]);
    }

    //todo
    public function search(Request $request): Response {
        if($request->has('query')) {
            //todo implement along with other filters
        }

        $results = [];

        return $this->view('pages/show', ['page' => 1, 'results' => $results]);
    }

    // // todo property page
    public function show(int $id, Request $request): Response
    {
        try {
            $pageData = $this->pageService->getPageWithBlocks($id);

            if (!$pageData) {
                return $this->view('errors/404', [
                    'message' => 'Page not found'
                ]);
            }

            return $this->view('pages/show', [
                'page' => $pageData['page'],
                'blocks' => $pageData['blocks'],
                'title' => $pageData['page']['title']
            ]);

        } catch (Exception $e) {
            return $this->view('errors/500', [
                'message' => 'Failed to load page'
            ]);
        }
    }

    // Web method - returns Response
    public function create(Request $request): Response
    {
        return $this->view('pages/create', [
            'title' => 'Create New Page',
            'available_block_types' => $this->blockRegistry->getAvailableTypes()
        ]);
    }

    // Web method - returns Response
    public function edit(int $id, Request $request): Response
    {
        try {
            $pageData = $this->pageService->getPageWithBlocks($id);

            if (!$pageData) {
                return $this->view('errors/404', [
                    'message' => 'Page not found'
                ]);
            }

            return $this->view('pages/edit', [
                'page' => $pageData['page'],
                'blocks' => $pageData['blocks'],
                'title' => 'Edit Page: ' . $pageData['page']['title'],
                'available_block_types' => $this->blockRegistry->getAvailableTypes()
            ]);

        } catch (Exception $e) {
            return $this->view('errors/500', [
                'message' => 'Failed to load page for editing'
            ]);
        }
    }

    // todo maybe we can repurpose this for the contact form
    public function store(Request $request): Response
    {
        try {
            $pageData = $request->only(['title', 'slug', 'status', 'meta_title', 'meta_description']);
            $blocksData = $request->get('blocks', []);

            $page = $this->pageService->createPageWithBlocks($pageData, $blocksData);

            // Redirect to the created page
            return $this->redirectResponse('/pages/' . $page->id);

        } catch (ValidationException $e) {
            // Return form with errors
            return $this->view('pages/create', [
                'title' => 'Create New Page',
                'errors' => $e->getValidationResult()->getErrors(),
                'old_data' => $request->all(),
                'available_block_types' => $this->blockRegistry->getAvailableTypes()
            ]);
        } catch (Exception $e) {
            return $this->view('errors/500', [
                'message' => 'Failed to create page'
            ]);
        }
    }

    // Web method - returns Response (redirect after update)
    public function update(int $id, Request $request): Response
    {
        try {
            $pageData = $request->only(['title', 'slug', 'status', 'meta_title', 'meta_description']);
            $blocksData = $request->get('blocks', []);

            $page = $this->pageService->updatePageWithBlocks($id, $pageData, $blocksData);

            return $this->redirectResponse('/pages/' . $id);

        } catch (ValidationException $e) {
            // Return form with errors
            $pageData = $this->pageService->getPageWithBlocks($id);

            return $this->view('pages/edit', [
                'page' => $pageData['page'],
                'blocks' => $pageData['blocks'],
                'title' => 'Edit Page: ' . $pageData['page']['title'],
                'errors' => $e->getValidationResult()->getErrors(),
                'available_block_types' => $this->blockRegistry->getAvailableTypes()
            ]);
        } catch (Exception $e) {
            return $this->view('errors/500', [
                'message' => 'Failed to update page'
            ]);
        }
    }

    // Web method - returns Response (redirect after delete)
    public function destroy(int $id, Request $request): Response
    {
        try {
            $result = $this->pageService->deletePage($id);

            if (!$result) {
                return $this->view('errors/404', [
                    'message' => 'Page not found'
                ]);
            }

            return $this->redirectResponse('/pages');

        } catch (Exception $e) {
            return $this->view('errors/500', [
                'message' => 'Failed to delete page'
            ]);
        }
    }
}