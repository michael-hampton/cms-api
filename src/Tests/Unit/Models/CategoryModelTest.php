<?php

namespace App\Tests\Unit\Models;

use App\Models\Category;
use App\Models\Page;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CategoryModelTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateCategory()
    {
        $category = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Tech articles',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertEquals('Technology', $category->name);
    }

    public function testCategoryWithParent()
    {
        $parent = Category::create([
            'name' => 'Parent Category',
            'slug' => 'parent',
            'is_active' => true,
        ]);

        $child = Category::create([
            'name' => 'Child Category',
            'slug' => 'child',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertInstanceOf(Category::class, $child->parent());
        $this->assertEquals('Parent Category', $child->parent()->name);
    }

    public function testCategoryChildren()
    {
        $parent = Category::create([
            'name' => 'Parent',
            'slug' => 'parent',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Child 1',
            'slug' => 'child-1',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Child 2',
            'slug' => 'child-2',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $children = $parent->children();
        $this->assertEquals(2, $children->count());
    }

    public function testCategoryBelongsToManyPages()
    {
        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
        ]);

        $this->database->insert('page_categories', [
            'page_id' => $page->id,
            'category_id' => $category->id,
        ]);

        $pages = $category->pages();
        $this->assertCount(1, $pages);
        $this->assertEquals('Test Page', $pages->first()->title);
    }

    public function testMetaAttributeGetterSetter()
    {
        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'is_active' => true,
        ]);

        $meta = ['key1' => 'value1', 'key2' => 'value2'];
        $category->setMetaAttribute($meta);
        $category->save();

        $fresh = Category::find($category->id);
        $this->assertEquals($meta, $fresh->getMetaAttribute());
    }

    public function testIsActive()
    {
        $active = Category::create([
            'name' => 'Active',
            'slug' => 'active',
            'is_active' => true,
        ]);

        $inactive = Category::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function testHasParent()
    {
        $parent = Category::create([
            'name' => 'Parent',
            'slug' => 'parent',
            'is_active' => true,
        ]);

        $child = Category::create([
            'name' => 'Child',
            'slug' => 'child',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $this->assertTrue($child->hasParent());
        $this->assertFalse($parent->hasParent());
    }

    public function testIsRootCategory()
    {
        $root = Category::create([
            'name' => 'Root',
            'slug' => 'root',
            'is_active' => true,
        ]);

        $child = Category::create([
            'name' => 'Child',
            'slug' => 'child',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);

        $this->assertTrue($root->isRootCategory());
        $this->assertFalse($child->isRootCategory());
    }

    public function testScopeActive()
    {
        Category::create(['name' => 'Active', 'slug' => 'active', 'is_active' => true]);
        Category::create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);

        $active = Category::active()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->name);
    }

    public function testScopeRoots()
    {
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'is_active' => true]);
        Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'is_active' => true]);

        $roots = Category::roots()->get();
        $this->assertCount(1, $roots);
        $this->assertEquals('Parent', $roots->first()->name);
    }

    public function testScopeBySlug()
    {
        Category::create(['name' => 'Cat 1', 'slug' => 'cat-1', 'is_active' => true]);
        Category::create(['name' => 'Cat 2', 'slug' => 'cat-2', 'is_active' => true]);

        $category = Category::bySlug('cat-1')->first();
        $this->assertEquals('Cat 1', $category->name);
    }

    public function testScopeOrdered()
    {
        Category::create(['name' => 'Z Category', 'slug' => 'z', 'sort_order' => 3, 'is_active' => true]);
        Category::create(['name' => 'A Category', 'slug' => 'a', 'sort_order' => 1, 'is_active' => true]);
        Category::create(['name' => 'M Category', 'slug' => 'm', 'sort_order' => 2, 'is_active' => true]);

        $ordered = Category::ordered()->get();
        $this->assertEquals('A Category', $ordered->first()->name);
        $this->assertEquals('M Category', $ordered->get(1)->name);
        $this->assertEquals('Z Category', $ordered->get(2)->name);
    }

    public function testGetBreadcrumb()
    {
        $grandparent = Category::create(['name' => 'Grandparent', 'slug' => 'grandparent', 'is_active' => true]);
        $parent = Category::create(['name' => 'Parent', 'slug' => 'parent', 'parent_id' => $grandparent->id, 'is_active' => true]);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'is_active' => true]);

        $breadcrumb = $child->getBreadcrumb();

        $this->assertCount(3, $breadcrumb);
        $this->assertEquals('Grandparent', $breadcrumb[0]['name']);
        $this->assertEquals('Parent', $breadcrumb[1]['name']);
        $this->assertEquals('Child', $breadcrumb[2]['name']);
    }

    public function testColorAndIconAttributes()
    {
        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech',
            'color' => '#FF5733',
            'icon' => 'fa-laptop',
            'is_active' => true,
        ]);

        $this->assertEquals('#FF5733', $category->color);
        $this->assertEquals('fa-laptop', $category->icon);
    }

    public function testUpdateCategory()
    {
        $category = Category::create([
            'name' => 'Original',
            'slug' => 'original',
            'is_active' => true,
        ]);

        $category->update([
            'name' => 'Updated',
            'description' => 'Updated description',
        ]);

        $fresh = Category::find($category->id);
        $this->assertEquals('Updated', $fresh->name);
        $this->assertEquals('Updated description', $fresh->description);
    }

    public function testDeleteCategory()
    {
        $category = Category::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'is_active' => true,
        ]);

        $id = $category->id;
        $category->delete();

        $deleted = Category::find($id);
        $this->assertNull($deleted);
    }
}