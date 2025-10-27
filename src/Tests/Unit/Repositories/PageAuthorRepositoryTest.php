<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Author;
use App\Models\Page;
use App\Models\PageAuthor;
use App\Repositories\PageAuthorRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageAuthorRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageAuthorRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageAuthorRepository();
    }

    public function test_sync_authors_removes_existing_authors_for_role(): void
    {
        $page = $this->createPage();
        $oldAuthor = $this->createAuthor(['name' => 'Old Author']);

        $this->attachAuthorToPage($page, $oldAuthor, ['role' => 'contributor']);

        $newAuthor = $this->createAuthor(['name' => 'New Author']);

        $this->repository->syncAuthors($page->id, [$newAuthor->id], 'contributor', $this->siteId);

        $this->assertDatabaseMissing('page_authors', [
            'page_id' => $page->id,
            'author_id' => $oldAuthor->id,
            'role' => 'contributor'
        ]);

        $this->assertDatabaseHas('page_authors', [
            'page_id' => $page->id,
            'author_id' => $newAuthor->id,
            'role' => 'contributor'
        ]);
    }

    public function test_sync_authors_only_removes_specific_role(): void
    {
        $page = $this->createPage();
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);

        $this->attachAuthorToPage($page, $author1, ['role' => 'primary']);
        $this->attachAuthorToPage($page, $author2, ['role' => 'contributor']);

        $newAuthor = $this->createAuthor(['name' => 'New Author']);

        $this->repository->syncAuthors($page->id, [$newAuthor->id], 'contributor', $this->siteId);

        // Primary author should still exist
        $this->assertDatabaseHas('page_authors', [
            'page_id' => $page->id,
            'author_id' => $author1->id,
            'role' => 'primary'
        ]);

        // Old contributor removed
        $this->assertDatabaseMissing('page_authors', [
            'page_id' => $page->id,
            'author_id' => $author2->id,
            'role' => 'contributor'
        ]);

        // New contributor added
        $this->assertDatabaseHas('page_authors', [
            'page_id' => $page->id,
            'author_id' => $newAuthor->id,
            'role' => 'contributor'
        ]);
    }

    public function test_sync_authors_maintains_sort_order(): void
    {
        $page = $this->createPage();
        $author1 = $this->createAuthor(['name' => 'Author 1']);
        $author2 = $this->createAuthor(['name' => 'Author 2']);
        $author3 = $this->createAuthor(['name' => 'Author 3']);

        $this->repository->syncAuthors(
            $page->id,
            [$author1->id, $author2->id, $author3->id],
            'contributor',
            $this->siteId
        );

        $pageAuthors = PageAuthor::where('page_id', $page->id)
            ->where('role', 'contributor')
            ->orderBy('sort_order')
            ->get();

        $pageAuthors = $pageAuthors->toArray();

        $this->assertCount(3, $pageAuthors);
        $this->assertEquals(0, $pageAuthors[0]['sort_order']);
        $this->assertEquals(1, $pageAuthors[1]['sort_order']);
        $this->assertEquals(2, $pageAuthors[2]['sort_order']);
        $this->assertEquals($author1->id, $pageAuthors[0]['author_id']);
        $this->assertEquals($author2->id, $pageAuthors[1]['author_id']);
        $this->assertEquals($author3->id, $pageAuthors[2]['author_id']);
    }

    public function test_sync_authors_with_empty_array_removes_all_for_role(): void
    {
        $page = $this->createPage();
        $author = $this->createAuthor();

        $this->attachAuthorToPage($page, $author, ['role' => 'contributor']);

        $this->repository->syncAuthors($page->id, [], 'contributor', $this->siteId);

        $count = $this->countRecords('page_authors', [
            'page_id' => $page->id,
            'role' => 'contributor'
        ]);

        $this->assertEquals(0, $count);
    }

    public function test_sync_authors_handles_multiple_roles_independently(): void
    {
        $page = $this->createPage();
        $primaryAuthor = $this->createAuthor(['name' => 'Primary']);
        $contributor1 = $this->createAuthor(['name' => 'Contributor 1']);
        $contributor2 = $this->createAuthor(['name' => 'Contributor 2']);

        // Set up initial state
        $this->repository->syncAuthors($page->id, [$primaryAuthor->id], 'primary', $this->siteId);
        $this->repository->syncAuthors($page->id, [$contributor1->id], 'contributor', $this->siteId);

        // Update only contributors
        $this->repository->syncAuthors($page->id, [$contributor2->id], 'contributor', $this->siteId);

        // Primary should be unchanged
        $this->assertDatabaseHas('page_authors', [
            'page_id' => $page->id,
            'author_id' => $primaryAuthor->id,
            'role' => 'primary'
        ]);

        // Contributor should be updated
        $this->assertDatabaseHas('page_authors', [
            'page_id' => $page->id,
            'author_id' => $contributor2->id,
            'role' => 'contributor'
        ]);

        $this->assertDatabaseMissing('page_authors', [
            'page_id' => $page->id,
            'author_id' => $contributor1->id,
            'role' => 'contributor'
        ]);
    }
}