<?php

namespace App\Controllers;

use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Repositories\PageRepository;
use App\Services\BlockParserService;

class PreviewController extends Controller
{
    private BlockParserService $blockParserService;
    private PageRepository $pageRepository;

    public function __construct(
        BlockParserService $blockParserService,
        PageRepository     $pageRepository
    )
    {
        $this->blockParserService = $blockParserService;
        $this->pageRepository = $pageRepository;

        parent::__construct();
    }

    public function preview(Request $request): JsonResponse
    {
        try {
            $pageId = $request->input('page_id');
            $blocks = $request->input('blocks', []);

            if (!$pageId) {
                return $this->jsonResponse([
                    'error' => 'Page ID is required'
                ], 400);
            }

            $page = $this->pageRepository->find($pageId);

            if (!$page) {
                return $this->jsonResponse([
                    'error' => 'Page not found'
                ], 404);
            }

            if (empty($blocks)) {
                $blocks = $page->blocks->toArray();
            }

            $html = $this->buildPreviewHtml($page, $blocks);

            return $this->jsonResponse([
                'html' => $html,
                'page' => [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'meta_description' => $page->meta_description
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => 'Failed to generate preview',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function buildPreviewHtml($page, array $blocks): string
    {
        $blocksHtml = '';

        foreach ($blocks as $index => $blockData) {
            try {

                $blockData = isset($blockData['data']) ? array_merge(json_decode($blockData['data'], true), ['type' => $blockData['type']]) : $blockData;

                $blockHtml = $this->blockParserService->buildBlock(
                    $page->id,
                    $blockData,
                    $index,
                    true
                );

                $blocksHtml .= $blockHtml;
            } catch (\Exception $e) {
                // Include error in preview
                $blocksHtml .= $this->buildErrorBlock($blockData['type'] ?? 'unknown', $e->getMessage());
            }
        }

        return $this->wrapInTemplate($page, $blocksHtml);
    }

    private function buildErrorBlock(string $type, string $message): string
    {
        return <<<HTML
        <div class="preview-error-block" style="border: 2px dashed #ff4444; padding: 1rem; margin: 1rem 0; background: #fff5f5;">
            <h4 style="color: #ff4444; margin: 0 0 0.5rem 0;">Error in {$type} block</h4>
            <p style="margin: 0; color: #666;">{$message}</p>
        </div>
        HTML;
    }

    private function wrapInTemplate($page, string $content): string
    {
        $descriptionHtml = '';
        if (!empty($page->meta_description)) {
            $descriptionHtml = ' | <span>Description: ' . htmlspecialchars($page->meta_description) . '</span>';
        }

        $cssPath = __DIR__ . '/../public/css/themes/estate.css';
        $css = file_exists($cssPath) ? file_get_contents($cssPath) : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$page->title} - Preview</title>
            <meta name="description" content="{$page->meta_description}">
             <style>
                {$css}
             </style>
            <link rel="stylesheet" href="/css/preview.css">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 2rem;
                    background: #f5f5f5;
                }
                .preview-header {
                    background: #fff;
                    padding: 2rem;
                    border-radius: 8px;
                    margin-bottom: 2rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .preview-header h1 {
                    margin: 0 0 0.5rem 0;
                    color: #1a1a1a;
                }
                .preview-header .meta {
                    color: #666;
                    font-size: 0.875rem;
                }
                .preview-content {
                    background: #fff;
                    padding: 2rem;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .preview-banner {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 1rem;
                    text-align: center;
                    position: sticky;
                    top: 0;
                    z-index: 1000;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                }
            </style>
        </head>
        <body>
            <div class="preview-banner">
                ⚠️ PREVIEW MODE - This is how your page will look when published
            </div>
            
            <div class="preview-header">
                <h1>{$page->title}</h1>
                <div class="meta">
                    <span>Slug: /{$page->slug}</span>{$descriptionHtml}
                </div>
            </div>
            
            <div class="preview-content">
                {$content}
            </div>
            
            <script>
                // Disable all links in preview
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('a').forEach(function(link) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            alert('Links are disabled in preview mode');
                        });
                    });
                    
                    // Disable form submissions
                    document.querySelectorAll('form').forEach(function(form) {
                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            alert('Forms are disabled in preview mode');
                        });
                    });
                });
            </script>
        </body>
        </html>
        HTML;
    }
}