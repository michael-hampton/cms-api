<?php

namespace App\Tests\Unit\Repositories;

use App\Repositories\Cms\AuthorRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

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
}