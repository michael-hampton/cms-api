<?php

namespace App\Tests\Unit\Models;

use App\Models\Author;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PageAuthorModelTest extends FunctionalTestCase
{
    public function testPageRelationship()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $author = Author::create([
            'name' => 'Test Author',
            'email' => 'test@example.com',
            'slug' => 'test-author',
            'status' => 'active'
        ]);

        $pageAuthor = PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author->id,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        $this->assertInstanceOf(Page::class, $pageAuthor->page());
        $this->assertEquals($page->id, $pageAuthor->page()->id);
    }

    public function testAuthorRelationship()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $author = Author::create([
            'name' => 'Test Author',
            'email' => 'test@example.com',
            'slug' => 'test-author',
            'status' => 'active'
        ]);

        $pageAuthor = PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author->id,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        $this->assertInstanceOf(Author::class, $pageAuthor->author());
        $this->assertEquals($author->id, $pageAuthor->author()->id);
    }

    public function testPrimaryScope()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $author1 = Author::create([
            'name' => 'Primary Author',
            'email' => 'primary@example.com',
            'slug' => 'primary-author',
            'status' => 'active'
        ]);

        $author2 = Author::create([
            'name' => 'Contributor',
            'email' => 'contributor@example.com',
            'slug' => 'contributor',
            'status' => 'active'
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author1->id,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author2->id,
            'role' => 'contributor',
            'sort_order' => 2
        ]);

        $primaryAuthors = PageAuthor::primary()->get();

        $this->assertCount(1, $primaryAuthors);
        $this->assertEquals('primary', $primaryAuthors->first()->role);
    }

    public function testContributorScope()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $author1 = Author::create([
            'name' => 'Primary Author',
            'email' => 'primary@example.com',
            'slug' => 'primary-author',
            'status' => 'active'
        ]);

        $author2 = Author::create([
            'name' => 'Contributor',
            'email' => 'contributor@example.com',
            'slug' => 'contributor',
            'status' => 'active'
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author1->id,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author2->id,
            'role' => 'contributor',
            'sort_order' => 2
        ]);

        $contributors = PageAuthor::contributor()->get();

        $this->assertCount(1, $contributors);
        $this->assertEquals('contributor', $contributors->first()->role);
    }

    public function testOrderedScope()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $author1 = Author::create([
            'name' => 'Author 1',
            'email' => 'author1@example.com',
            'slug' => 'author-1',
            'status' => 'active'
        ]);

        $author2 = Author::create([
            'name' => 'Author 2',
            'email' => 'author2@example.com',
            'slug' => 'author-2',
            'status' => 'active'
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author1->id,
            'role' => 'primary',
            'sort_order' => 2
        ]);

        PageAuthor::create([
            'page_id' => $page->id,
            'author_id' => $author2->id,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        $orderedAuthors = PageAuthor::ordered()->get();

        $this->assertEquals(1, $orderedAuthors->first()->sort_order);
        $this->assertEquals(2, $orderedAuthors->last()->sort_order);
    }

    public function testFillableAttributes()
    {
        $pageAuthor = new PageAuthor([
            'page_id' => 1,
            'author_id' => 2,
            'role' => 'primary',
            'sort_order' => 1
        ]);

        $this->assertEquals(1, $pageAuthor->page_id);
        $this->assertEquals(2, $pageAuthor->author_id);
        $this->assertEquals('primary', $pageAuthor->role);
        $this->assertEquals(1, $pageAuthor->sort_order);
    }
}