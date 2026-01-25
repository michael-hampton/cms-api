<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Repositories\Cms\AuthorRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class AuthorRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private AuthorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AuthorRepository();
    }

    public function test_it_can_find_author_by_slug(): void
    {
        $author = $this->createAuthor(['slug' => 'john-doe', 'name' => 'John Doe']);

        $found = $this->repository->findBySlug('john-doe');

        $this->assertNotNull($found);
        $this->assertEquals($author->id, $found->id);
        $this->assertEquals('john-doe', $found->slug);
    }

    public function test_it_returns_null_when_slug_not_found(): void
    {
        $found = $this->repository->findBySlug('non-existent-slug');

        $this->assertNull($found);
    }

    public function test_it_can_find_author_by_email(): void
    {
        $author = $this->createAuthor([
            'email' => 'john@example.com',
            'name' => 'John Doe'
        ]);

        $found = $this->repository->findByEmail('john@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($author->id, $found->id);
        $this->assertEquals('john@example.com', $found->email);
    }

    public function test_it_returns_null_when_email_not_found(): void
    {
        $found = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($found);
    }

    public function test_it_returns_active_authors_only(): void
    {
        $this->createAuthor(['status' => 'active', 'name' => 'Active 1']);
        $this->createAuthor(['status' => 'active', 'name' => 'Active 2']);
        $this->createAuthor(['status' => 'inactive', 'name' => 'Inactive']);

        $authors = $this->repository->getActiveAuthors();

        $this->assertCount(2, $authors);
        foreach ($authors as $author) {
            $this->assertEquals('active', $author->status);
        }
    }

    public function test_active_authors_ordered_by_name(): void
    {
        $this->createAuthor(['status' => 'active', 'name' => 'Charlie']);
        $this->createAuthor(['status' => 'active', 'name' => 'Alice']);
        $this->createAuthor(['status' => 'active', 'name' => 'Bob']);

        $authors = $this->repository->getActiveAuthors();
        $names = $authors->pluck('name')->toArray();

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $names);
    }

    public function test_search_authors_by_name(): void
    {
        $this->createAuthor(['name' => 'John Doe']);
        $this->createAuthor(['name' => 'Jane Smith']);
        $this->createAuthor(['name' => 'John Smith']);

        $results = $this->repository->searchAuthors('John');

        $this->assertCount(2, $results);
        foreach ($results as $author) {
            $this->assertStringContainsString('John', $author->name);
        }
    }

    public function test_search_authors_by_email(): void
    {
        $this->createAuthor(['email' => 'john@example.com', 'name' => 'John']);
        $this->createAuthor(['email' => 'jane@test.com', 'name' => 'Jane']);

        $results = $this->repository->searchAuthors('example.com');

        $this->assertCount(1, $results);
        $this->assertEquals('john@example.com', $results->first()->email);
    }

    public function test_search_authors_by_bio(): void
    {
        $this->createAuthor([
            'name' => 'Author 1',
            'bio' => 'Expert in technology'
        ]);
        $this->createAuthor([
            'name' => 'Author 2',
            'bio' => 'Expert in cooking'
        ]);

        $results = $this->repository->searchAuthors('technology');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('technology', $results->first()->bio);
    }

    public function test_search_authors_respects_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createAuthor(['name' => "Author {$i}"]);
        }

        $results = $this->repository->searchAuthors('Author', 5);

        $this->assertCount(5, $results);
    }

    public function test_search_authors_ordered_by_name(): void
    {
        $this->createAuthor(['name' => 'Zack']);
        $this->createAuthor(['name' => 'Alice']);
        $this->createAuthor(['name' => 'Mike']);

        $results = $this->repository->searchAuthors('');
        $names = $results->pluck('name')->toArray();

        $this->assertEquals('Alice', $names[0]);
    }

    public function test_get_pages_by_author_id(): void
    {
        $author = $this->createAuthor();
        $page1 = $this->createPage(['author_id' => $author->id, 'title' => 'Page 1']);
        $page2 = $this->createPage(['author_id' => $author->id, 'title' => 'Page 2']);
        $otherPage = $this->createPage(['title' => 'Other Page']);

        $pages = $this->repository->getPagesByAuthorId($author->id);

        $this->assertCount(2, $pages);
        $this->assertCollectionContains($pages, ['title' => 'Page 1']);
        $this->assertCollectionContains($pages, ['title' => 'Page 2']);
    }

    public function test_get_pages_by_author_id_respects_limit(): void
    {
        $author = $this->createAuthor();
        for ($i = 1; $i <= 10; $i++) {
            $this->createPage(['author_id' => $author->id]);
        }

        $pages = $this->repository->getPagesByAuthorId($author->id, 5);

        $this->assertCount(5, $pages);
    }

    public function test_get_alternatives_excludes_specified_author(): void
    {
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);
        $author3 = $this->createAuthor(['name' => 'Author 3']);

        $alternatives = $this->repository->getAlternatives($author1->id);

        $this->assertCount(2, $alternatives);
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $author1->id]);
        $this->assertCollectionContains($alternatives, ['id' => $author2->id]);
        $this->assertCollectionContains($alternatives, ['id' => $author3->id]);
    }

    public function test_search_returns_paginated_result(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->createAuthor(['name' => "Author {$i}"]);
        }

        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $criteria->setPage(1);

        $result = $this->repository->search($criteria);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertCount(10, $result->getData());
    }

    public function test_find_or_create_from_user_finds_existing_author_by_email(): void
    {
        // Create an existing author
        $existingAuthor = $this->createAuthor([
            'email' => 'existing@example.com',
            'name' => 'Existing Author',
            'site_id' => $this->siteId
        ]);

        // Create a user with the same email
        $user = $this->createUser([
            'email' => 'existing@example.com',
            'name' => 'User Name'
        ]);

        // Call the method
        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        // Should return the existing author
        $this->assertEquals($existingAuthor->id, $result->id);
        $this->assertEquals('existing@example.com', $result->email);
        $this->assertEquals('Existing Author', $result->name);

        // Should not create a new author
        $count = $this->countRecords('authors', ['email' => 'existing@example.com']);
        $this->assertEquals(1, $count);
    }

    public function test_find_or_create_from_user_creates_new_author_when_not_exists(): void
    {
        $user = $this->createUser([
            'email' => 'newauthor@example.com',
            'name' => 'New Author'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        // Should create a new author
        $this->assertNotNull($result);
        $this->assertEquals('newauthor@example.com', $result->email);
        $this->assertEquals('New Author', $result->name);
        $this->assertEquals($this->siteId, $result->site_id);
        $this->assertEquals('active', $result->status);

        // Verify it exists in database
        $count = $this->countRecords('authors', ['email' => 'newauthor@example.com']);
        $this->assertEquals(1, $count);
    }

    public function test_find_or_create_from_user_generates_unique_slug(): void
    {
        $user = $this->createUser([
            'email' => 'slugtest@example.com',
            'name' => 'John Doe'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertEquals('john-doe', $result->slug);
    }

    public function test_find_or_create_from_user_generates_unique_slug_when_duplicate_exists(): void
    {
        // Create an existing author with the slug
        $this->createAuthor([
            'name' => 'Jane Smith',
            'slug' => 'jane-smith',
            'email' => 'jane1@example.com',
            'site_id' => $this->siteId
        ]);

        // Create another author with same name but different email
        $user = $this->createUser([
            'email' => 'jane2@example.com',
            'name' => 'Jane Smith'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        // Should have a unique slug with number suffix
        $this->assertEquals('jane-smith-1', $result->slug);
        $this->assertEquals('jane2@example.com', $result->email);
    }

    public function test_find_or_create_from_user_increments_slug_counter_for_multiple_duplicates(): void
    {
        // Create existing authors
        $this->createAuthor([
            'name' => 'Bob Jones',
            'slug' => 'bob-jones',
            'email' => 'bob1@example.com',
            'site_id' => $this->siteId
        ]);

        $this->createAuthor([
            'name' => 'Bob Jones',
            'slug' => 'bob-jones-1',
            'email' => 'bob2@example.com',
            'site_id' => $this->siteId
        ]);

        $user = $this->createUser([
            'email' => 'bob3@example.com',
            'name' => 'Bob Jones'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertEquals('bob-jones-2', $result->slug);
    }

    public function test_find_or_create_from_user_sets_status_to_active(): void
    {
        $user = $this->createUser([
            'email' => 'statustest@example.com',
            'name' => 'Status Test'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertEquals('active', $result->status);
    }

    public function test_find_or_create_from_user_sets_empty_bio(): void
    {
        $user = $this->createUser([
            'email' => 'biotest@example.com',
            'name' => 'Bio Test'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertEquals('', $result->bio);
    }

    public function test_find_or_create_from_user_handles_special_characters_in_name(): void
    {
        $user = $this->createUser([
            'email' => 'special@example.com',
            'name' => 'Jöhn Dœ\'s Authör!'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        // Should create a clean slug
        $this->assertEquals('j-hn-d-s-auth-r', $result->slug);
        $this->assertEquals('Jöhn Dœ\'s Authör!', $result->name);
    }

    public function test_find_or_create_from_user_handles_name_with_spaces(): void
    {
        $user = $this->createUser([
            'email' => 'spaces@example.com',
            'name' => 'John   Middle   Doe'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertEquals('john-middle-doe', $result->slug);
    }

    public function test_find_or_create_from_user_finds_author_case_insensitive_email(): void
    {
        $existingAuthor = $this->createAuthor([
            'email' => 'UPPERCASE@example.com',
            'name' => 'Original Name',
            'site_id' => $this->siteId
        ]);

        $user = $this->createUser([
            'email' => 'uppercase@example.com',
            'name' => 'Different Name'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        // Should find the existing author (emails should be case-insensitive)
        // Note: This depends on your database collation and findByEmail implementation
        $this->assertEquals($existingAuthor->id, $result->id);
    }

    public function test_find_or_create_from_user_sets_timestamps(): void
    {
        $user = $this->createUser([
            'email' => 'timestamps@example.com',
            'name' => 'Timestamp Test'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    public function test_find_or_create_from_user_handles_numeric_name(): void
    {
        $user = $this->createUser([
            'email' => 'numeric@example.com',
            'name' => '12345'
        ]);

        $result = $this->repository->findOrCreateFromUser($user, $this->siteId);

        $this->assertEquals('12345', $result->name);
        $this->assertEquals('12345', $result->slug);
    }
}