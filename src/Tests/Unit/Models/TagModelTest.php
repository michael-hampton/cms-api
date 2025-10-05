<?php

namespace App\Tests\Unit\Models;

use App\Models\Page;
use App\Models\Tag;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class TagModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateTag()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'description' => 'PHP programming language',
        ]);

        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertEquals('PHP', $tag->name);
        $this->assertEquals('php', $tag->slug);
    }

    public function testTagBelongsToManyPages()
    {
        $tag = Tag::create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $page = Page::create([
            'title' => 'Tech Article',
            'slug' => 'tech-article',
            'status' => 'published',
        ]);

        $this->database->insert('page_tags', [
            'page_id' => $page->id,
            'tag_id' => $tag->id,
        ]);

        $pages = $tag->pages(true)->get();
        $this->assertCount(1, $pages);
        $this->assertEquals('Tech Article', $pages->first()->title);
    }

    public function testMetaAttributeGetterSetter()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        $meta = ['priority' => 'high', 'category' => 'language'];
        $tag->setMetaAttribute($meta);
        $tag->save();

        $fresh = Tag::find($tag->id);
        $this->assertEquals($meta, $fresh->getMetaAttribute());
    }

    public function testMetaAttributeWithString()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        $jsonString = '{"key":"value"}';
        $tag->setMetaAttribute($jsonString);
        $tag->save();

        $fresh = Tag::find($tag->id);
        $this->assertEquals(['key' => 'value'], $fresh->getMetaAttribute());
    }

    public function testIsFeatured()
    {
        $featured = Tag::create([
            'name' => 'Featured',
            'slug' => 'featured',
            'is_featured' => true,
        ]);

        $normal = Tag::create([
            'name' => 'Normal',
            'slug' => 'normal',
            'is_featured' => false,
        ]);

        $this->assertTrue($featured->isFeatured());
        $this->assertFalse($normal->isFeatured());
    }

    public function testIncrementUsage()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'usage_count' => 5,
        ]);

        $tag->incrementUsage();

        $fresh = Tag::find($tag->id);
        $this->assertEquals(6, $fresh->usage_count);
    }

    public function testDecrementUsage()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'usage_count' => 5,
        ]);

        $tag->decrementUsage();

        $fresh = Tag::find($tag->id);
        $this->assertEquals(4, $fresh->usage_count);
    }

    public function testDecrementUsageDoesNotGoBelowZero()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'usage_count' => 0,
        ]);

        $tag->decrementUsage();

        $fresh = Tag::find($tag->id);
        $this->assertEquals(0, $fresh->usage_count);
    }

    public function testScopeFeatured()
    {
        Tag::create(['name' => 'Featured', 'slug' => 'featured', 'is_featured' => true]);
        Tag::create(['name' => 'Normal', 'slug' => 'normal', 'is_featured' => false]);

        $featured = Tag::featured()->get();
        $this->assertCount(1, $featured);
        $this->assertEquals('Featured', $featured->first()->name);
    }

    public function testScopePopular()
    {
        Tag::create(['name' => 'Tag1', 'slug' => 'tag1', 'usage_count' => 100]);
        Tag::create(['name' => 'Tag2', 'slug' => 'tag2', 'usage_count' => 50]);
        Tag::create(['name' => 'Tag3', 'slug' => 'tag3', 'usage_count' => 75]);

        $popular = Tag::popular(2)->get();
        $this->assertCount(2, $popular);
        $this->assertEquals('Tag1', $popular->first()->name);
        $this->assertEquals('Tag3', $popular->get(1)->name);
    }

    public function testScopeBySlug()
    {
        Tag::create(['name' => 'Tag1', 'slug' => 'tag-1']);
        Tag::create(['name' => 'Tag2', 'slug' => 'tag-2']);

        $tag = Tag::bySlug('tag-1')->first();
        $this->assertEquals('Tag1', $tag->name);
    }

    public function testUsageCountCast()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'usage_count' => '42',
        ]);

        $this->assertIsInt($tag->usage_count);
        $this->assertEquals(42, $tag->usage_count);
    }

    public function testColorAttribute()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
            'color' => '#007BFF',
        ]);

        $this->assertEquals('#007BFF', $tag->color);
    }

    public function testUpdateTag()
    {
        $tag = Tag::create([
            'name' => 'Original',
            'slug' => 'original',
        ]);

        $tag->update([
            'name' => 'Updated',
            'description' => 'Updated description',
        ]);

        $fresh = Tag::find($tag->id);
        $this->assertEquals('Updated', $fresh->name);
        $this->assertEquals('Updated description', $fresh->description);
    }

    public function testDeleteTag()
    {
        $tag = Tag::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
        ]);

        $id = $tag->id;
        $tag->delete();

        $deleted = Tag::find($id);
        $this->assertNull($deleted);
    }

    public function testTimestamps()
    {
        $tag = Tag::create([
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        $this->assertNotNull($tag->created_at);
        $this->assertNotNull($tag->updated_at);
    }
}