<?php

namespace App\Tests\Unit\Models;

use App\Models\Block;
use App\Models\Page;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class BlockModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateBlock()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => ['content' => 'Block content'],
            'order' => 1,
        ]);

        $this->assertInstanceOf(Block::class, $block);
        $this->assertEquals('text', $block->type);
    }

    public function testBlockBelongsToPage()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => ['content' => 'Block content'],
            'order' => 1,
        ]);

        $blockPage = $block->page();
        $this->assertInstanceOf(Page::class, $blockPage);
        $this->assertEquals($page->id, $blockPage->id);
    }

    public function testDataAttributeGetter()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $data = ['title' => 'Block Title', 'content' => 'Block Content'];
        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'hero',
            'data' => $data,
            'order' => 1,
        ]);

        $fresh = Block::find($block->id);
        $this->assertEquals($data, $fresh->getDataAttribute());
    }

    public function testDataAttributeSetter()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'data' => ['content' => 'Block Content'],
            'type' => 'text',
            'order' => 1,
        ]);

        $data = ['heading' => 'New Heading', 'body' => 'New Body'];
        $block->setDataAttribute($data);
        $block->save();

        $fresh = Block::find($block->id);
        $this->assertEquals($data, $fresh->data);
    }

    public function testDataCast()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'custom',
            'data' => $data,
            'order' => 1,
        ]);

        $this->assertIsArray($block->data);
        $this->assertEquals($data, $block->data);
    }

    public function testOrderAttribute()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        Block::create(['page_id' => $page->id, 'type' => 'text', 'order' => 3, 'data' => ['content' => 'Block 1']]);;
        Block::create(['page_id' => $page->id, 'type' => 'text', 'order' => 1, 'data' => ['content' => 'Block 2']]);;;
        Block::create(['page_id' => $page->id, 'type' => 'text', 'order' => 2, 'data' => ['content' => 'Block 3']]);;;;

        $blocks = Block::where('page_id', $page->id)->orderBy('order')->get();
        $this->assertEquals(1, $blocks->first()->order);
        $this->assertEquals(2, $blocks->get(1)->order);
        $this->assertEquals(3, $blocks->get(2)->order);
    }

    public function testUpdateBlock()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'text',
            'data' => ['content' => 'Original'],
            'order' => 1,
        ]);

        $block->update([
            'data' => ['content' => 'Updated'],
            'order' => 2,
        ]);

        $fresh = Block::find($block->id);
        $this->assertEquals(['content' => 'Updated'], $fresh->data);
        $this->assertEquals(2, $fresh->order);
    }

    public function testDeleteBlock()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'draft',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'data' => ['content' => 'Block Content'],
            'type' => 'text',
            'order' => 1,
        ]);

        $id = $block->id;
        $block->delete();

        $deleted = Block::find($id);
        $this->assertNull($deleted);
    }
}