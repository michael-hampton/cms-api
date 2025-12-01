<?php

namespace App\Tests\Unit\Models;

use App\Models\Author;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AuthorModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    public function testCreateAuthor()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Author::class, $author);
        $this->assertEquals('John Doe', $author->name);
        $this->assertEquals('john@example.com', $author->email);
    }

    public function testAuthorHasManyPages()
    {
        $author = Author::create([
            'name' => 'Jane Smith',
            'slug' => 'jane-smith',
            'email' => 'jane@example.com',
            'status' => 'active',
        ]);

       $page1 = $this->createPage();
       $page2 = $this->createPage();

       $this->attachAuthorToPage($page1, $author);
       $this->attachAuthorToPage($page2, $author);

        $pages = $author->pages(true)->get();
        $this->assertEquals(2, $pages->count());
    }

    public function testIsActive()
    {
        $active = Author::create([
            'name' => 'Active Author',
            'slug' => 'active-author',
            'email' => 'active@example.com',
            'status' => 'active',
            'is_active' => true
        ]);

        $inactive = Author::create([
            'name' => 'Inactive Author',
            'slug' => 'inactive-author',
            'email' => 'inactive@example.com',
            'status' => 'inactive',
            'is_active' => false
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function testGetFullNameAttribute()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $this->assertEquals('John Doe', $author->getFullNameAttribute());
    }

    public function testGetUrlAttribute()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $this->assertEquals('/authors/john-doe', $author->getUrlAttribute());
    }

    public function testScopeActive()
    {
        Author::create(['name' => 'Active', 'slug' => 'active', 'email' => 'active@test.com', 'status' => 'active', 'is_active' => true]);
        Author::create(['name' => 'Inactive', 'slug' => 'inactive', 'email' => 'inactive@test.com', 'status' => 'inactive', 'is_active' => false]);

        $active = Author::active()->get();
        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->name);
    }

    public function testScopeBySlug()
    {
        Author::create(['name' => 'Author 1', 'slug' => 'author-1', 'email' => 'a1@test.com', 'status' => 'active']);
        Author::create(['name' => 'Author 2', 'slug' => 'author-2', 'email' => 'a2@test.com', 'status' => 'active']);

        $author = Author::bySlug('author-1')->first();
        $this->assertEquals('Author 1', $author->name);
    }

    public function testToArrayIncludesUrl()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $array = $author->toArray();
        $this->assertArrayHasKey('url', $array);
        $this->assertEquals('/authors/john-doe', $array['url']);
    }

    public function testAuthorWithBio()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'bio' => 'Experienced writer and blogger',
            'status' => 'active',
        ]);

        $this->assertEquals('Experienced writer and blogger', $author->bio);
    }

    public function testAuthorWithSocialLinks()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'twitter' => '@johndoe',
            'linkedin' => 'linkedin.com/in/johndoe',
            'facebook' => 'facebook.com/johndoe',
            'website' => 'https://johndoe.com',
            'status' => 'active',
        ]);

        $this->assertEquals('@johndoe', $author->twitter);
        $this->assertEquals('linkedin.com/in/johndoe', $author->linkedin);
        $this->assertEquals('facebook.com/johndoe', $author->facebook);
        $this->assertEquals('https://johndoe.com', $author->website);
    }

    public function testAuthorWithAvatar()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'avatar' => '/uploads/avatars/john-doe.jpg',
            'status' => 'active',
        ]);

        $this->assertEquals('/uploads/avatars/john-doe.jpg', $author->avatar);
    }

    public function testUpdateAuthor()
    {
        $author = Author::create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'email' => 'original@example.com',
            'status' => 'active',
        ]);

        $author->update([
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);

        $fresh = Author::find($author->id);
        $this->assertEquals('Updated Name', $fresh->name);
        $this->assertEquals('Updated bio', $fresh->bio);
    }

    public function testDeleteAuthor()
    {
        $author = Author::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'email' => 'delete@example.com',
            'status' => 'active',
        ]);

        $id = $author->id;
        $author->delete();

        $deleted = Author::find($id);
        $this->assertNull($deleted);
    }

    public function testTimestamps()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $this->assertNotNull($author->created_at);
        $this->assertNotNull($author->updated_at);
    }

    public function testAuthorWithExpertiseFields()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'expertise' => 'Web development, Content strategy',
            'location' => ['New York', 'Remote'],
            'education' => ['BS Computer Science', 'MS Web Development'],
            'awards' => ['Best Writer 2023', 'Excellence Award 2024'],
            'seniority_date' => '2020-01-15',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->assertEquals('Web development, Content strategy', $author->expertise);
        $this->assertEquals(['New York', 'Remote'], $author->location);
        $this->assertEquals(['BS Computer Science', 'MS Web Development'], $author->education);
        $this->assertEquals(['Best Writer 2023', 'Excellence Award 2024'], $author->awards);
        $this->assertEquals('2020-01-15', $author->seniority_date->format('Y-m-d'));
        $this->assertTrue($author->is_active);
    }

    public function testGetYearsOfExperienceAttribute()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'seniority_date' => '2020-01-01',
            'status' => 'active',
        ]);

        $years = $author->getYearsOfExperienceAttribute();
        $this->assertGreaterThanOrEqual(4, $years);
        $this->assertLessThanOrEqual(6, $years);
    }

    public function testGetTotalPublishedArticlesAttribute()
    {
        $author = $this->createAuthor();

        $page1 = $this->createPage(['status' => 'published']);
        $page2 = $this->createPage(['status' => 'published']);
        $page3 = $this->createPage(['status' => 'draft']);

        $this->attachAuthorToPage($page1, $author);
        $this->attachAuthorToPage($page2, $author);
        $this->attachAuthorToPage($page3, $author);

        $this->assertEquals(2, $author->getTotalPublishedArticlesAttribute());
    }

    public function testGetTotalPublishedReviewsAttribute()
    {
        $author = $this->createAuthor();

        $review1 = $this->createPage(['status' => 'published', 'page_type' => 'review']);
        $review2 = $this->createPage(['status' => 'published', 'page_type' => 'review']);
        $article = $this->createPage(['status' => 'published', 'page_type' => 'article']);

        $this->attachAuthorToPage($review1, $author);
        $this->attachAuthorToPage($review2, $author);
        $this->attachAuthorToPage($article, $author);

        $this->assertEquals(2, $author->getTotalPublishedReviewsAttribute());
    }

    public function testToArrayIncludesNewFields()
    {
        $author = Author::create([
            'name' => 'John Doe',
            'slug' => 'john-doe',
            'email' => 'john@example.com',
            'expertise' => 'Writing',
            'location' => ['NYC'],
            'seniority_date' => '2020-01-01',
            'status' => 'active',
        ]);

        $array = $author->toArray();

        $this->assertArrayHasKey('expertise', $array);
        $this->assertArrayHasKey('location', $array);
        $this->assertArrayHasKey('total_published_articles', $array);
        $this->assertArrayHasKey('total_published_reviews', $array);
        $this->assertArrayHasKey('years_of_experience', $array);
    }
}