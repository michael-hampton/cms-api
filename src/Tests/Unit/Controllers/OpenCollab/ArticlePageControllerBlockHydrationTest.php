<?php

namespace App\Tests\Unit\Controllers\OpenCollab;

use App\Controllers\OpenCollab\ArticlePageController;
use App\Framework\Support\Collection;
use App\Models\Block;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Services\OpenCollab\ArticleAccessService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\ReadabilityService;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ArticlePageControllerBlockHydrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_persisted_blocks_are_restored_for_open_collab_editor(): void
    {
        $controller = new ArticlePageController(
            Mockery::mock(PageRepository::class),
            Mockery::mock(ArticleAccessService::class),
            Mockery::mock(ReadabilityService::class),
            Mockery::mock(OpenCollabAuthorizationService::class),
        );

        $heading = new Block([
            'id' => 9,
            'type' => 'heading',
            'order' => 0,
            'data' => [
                'level' => 'h2',
                'text' => 'Existing article title',
                'subtitle' => '',
            ],
        ]);

        $text = new Block([
            'id' => 10,
            'type' => 'text',
            'order' => 1,
            'data' => [
                'paragraphs' => [
                    'Existing article content',
                    '<p>Second paragraph</p>',
                ],
            ],
        ]);

        $image = new Block([
            'id' => 11,
            'type' => 'image',
            'order' => 2,
            'data' => [
                'image_id' => 39,
                'src' => '/storage/uploads/image.jpg',
                'alt' => 'Existing image',
                'credit' => 'Photographer',
                'layout' => 'full',
                'alignment' => 'center',
            ],
        ]);

        $page = new Page([
            'id' => 5,
            'title' => 'Existing article title',
        ]);
        $page->blocks = Collection::make([$heading, $text, $image]);

        $method = new ReflectionMethod($controller, 'hydrateBlocksForEditor');
        $method->setAccessible(true);
        $method->invoke($controller, $page);

        $this->assertSame('__default_heading__', $heading->id);
        $this->assertSame('Existing article title', $heading->text);

        $this->assertSame('__default_text__', $text->id);
        $this->assertSame(
            '<p>Existing article content</p><p>Second paragraph</p>',
            $text->content,
        );

        $this->assertSame('__default_image__', $image->id);
        $this->assertSame(39, $image->cms_image_id);
        $this->assertSame('/storage/uploads/image.jpg', $image->image_url);
        $this->assertSame('/storage/uploads/image.jpg', $image->thumbnail_url);
        $this->assertSame('Existing image', $image->alt);
        $this->assertSame('Photographer', $image->credit);
    }
}
