<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Block;
use App\Models\Page;

class PreviewControllerTest extends FunctionalTestCase
{
    public function test_can_generate_preview_for_page()
    {
        $blocks = [
            [
                'type' => 'heading',
                'data' => ['text' => 'Test Heading', 'level' => 'h1']
            ],
            [
                'type' => 'text',
                'data' => ['paragraphs' => ['Test paragraph content']]
            ]
        ];

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'meta_description' => 'Test description',
            'site_id' => $this->siteId
        ]);

        foreach ($blocks as $block) {
            Block::create(array_merge($block, ['page_id' => $page->id]));
        }

        $response = $this->post('/api/preview', [
            'page_id' => $page->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('html', $data['data']);
        $this->assertArrayHasKey('page', $data['data']);
        $this->assertStringContainsString('Test Heading', $data['data']['html']);
        $this->assertStringContainsString('Test paragraph content', $data['data']['html']);
        $this->assertEquals('Test Page', $data['data']['page']['title']);

    }

    public function test_can_generate_preview_with_custom_blocks()
    {
        $blocks = [
            [
                'id' => 'block-1',
                'type' => 'heading',
                'text' => 'Test Heading',
                'level' => 'h1'
            ],
            [
                'id' => 'block-2',
                'type' => 'text',
                'paragraphs' => ['Test paragraph content']
            ]
        ];

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'meta_description' => 'Test description',
            'blocks' => $blocks,
            'site_id' => $this->siteId
        ]);

        $response = $this->post('/api/preview', [
            'page_id' => $page->id,
            'blocks' => $blocks
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('html', $data['data']);
        $this->assertArrayHasKey('page', $data['data']);
        $this->assertStringContainsString('Test Heading', $data['data']['html']);
        $this->assertStringContainsString('Test paragraph content', $data['data']['html']);
        $this->assertEquals('Test Page', $data['data']['page']['title']);
    }

    public function test_preview_requires_page_id()
    {
        $response = $this->post('/api/preview', [
            'blocks' => []
        ]);

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data['data']);
        $this->assertEquals('Page ID is required', $data['data']['error']);
    }

    public function test_preview_returns_404_for_nonexistent_page()
    {
        $response = $this->post('/api/preview', [
            'page_id' => 99999
        ]);

        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Page not found', $data['data']['error']);
    }

    public function test_preview_includes_css_styles()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'blocks' => [
                ['id' => 'block-1', 'type' => 'text', 'content' => 'Test']
            ],
            'site_id' => $this->siteId
        ]);

        $response = $this->post('/api/preview', [
            'page_id' => $page->id
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('<style>', $data['data']['html']);
        $this->assertStringContainsString('</style>', $data['data']['html']);
    }

    public function test_preview_includes_preview_banner()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'blocks' => [],
            'site_id' => $this->siteId
        ]);

        $response = $this->post('/api/preview', [
            'page_id' => $page->id
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('PREVIEW MODE', $data['data']['html']);
        $this->assertStringContainsString('preview-banner', $data['data']['html']);
    }

//    public function test_preview_handles_block_errors_gracefully()
//    {
//        $page = Page::create([
//            'title' => 'Test Page',
//            'slug' => 'test-page',
//            'blocks' => [
//                [
//                    'id' => 'block-1',
//                    'type' => 'invalid-block-type',
//                    'content' => 'Test'
//                ]
//            ],
//            'site_id' => $this->siteId
//        ]);
//
//        $response = $this->post('/api/preview', [
//            'page_id' => $page->id
//        ]);
//
//        $this->assertEquals(200, $response->getStatusCode());
//
//        $data = json_decode($response->getContent(), true);
//        $this->assertStringContainsString('preview-error-block', $data['data']['html']);
//    }

}