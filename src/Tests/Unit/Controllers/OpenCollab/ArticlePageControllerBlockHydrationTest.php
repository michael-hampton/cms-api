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

    public function test_persisted_block_data_is_flattened_for_open_collab_editor(): void
    {
        $controller = new ArticlePageController(
            Mockery::mock(PageRepository::class),
            Mockery::mock(ArticleAccessService::class),
            Mockery::mock(ReadabilityService::class),
            Mockery::mock(OpenCollabAuthorizationService::class),
        );

        $text = new Block([
            'id' => 10,
            'type' => 'text',
            'order' => 0,
            'data' => [
                'content' => '<p>Existing article content</p>',
            ],
        ]);

        $image = new Block([
            'id' => 11,
            'type' => 'image',
            'order' => 1,
            'data' => [
                'image_id' => 39,
                'src' => '/storage/uploads/image.jpg',
                'alt' => 'Existing image',
                'credit' => 'Photographer',
                'layout' => 'full',
                'alignment' => 'center',
            ],
        ]);

        $page = new Page(['id' => 5]);
        $page->blocks = Collection::make([$text, $image]);

        $method = new ReflectionMethod($controller, 'hydrateBlocksForEditor');
        $method->setAccessible(true);
        $method->invoke($controller, $page);

        $this->assertSame('<p>Existing article content</p>', $text->content);
        $this->assertSame(39, $image->cms_image_id);
        $this->assertSame('/storage/uploads/image.jpg', $image->image_url);
        $this->assertSame('/storage/uploads/image.jpg', $image->thumbnail_url);
        $this->assertSame('Existing image', $image->alt);
        $this->assertSame('Photographer', $image->credit);
    }
}
