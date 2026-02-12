<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\Brief;
use App\Models\Model;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Cms\CollaboratorRepository;
use App\Repositories\Cms\Pages\PageCollaboratorRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class CollaboratorRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private CollaboratorRepository $repository;
    private PageCollaboratorRepository $pageCollaboratorRepository;

    public function test_create_for_collaboratable_creates_collaborator(): void
    {
        // Arrange
        $page = $this->createPage();
        $user = $this->createUser();

        // Act
        $collaborator = $this->repository->createForCollaboratable(
            Page::class,
            $page->id,
            [
                'user_id' => $user->id,
                'role' => 'editor',
                'assigned_at' => now_datetime(),
                'site_id' => $this->siteId
            ], $this->siteId
        );

        // Assert
        $this->assertNotNull($collaborator->id);
        $this->assertEquals(Page::class, $collaborator->collaboratable_type);
        $this->assertEquals($page->id, $collaborator->collaboratable_id);
        $this->assertEquals($user->id, $collaborator->user_id);
        $this->assertEquals('editor', $collaborator->role);
    }

    private function createUser(array $data = []): Model
    {
        return User::create(array_merge([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'site_id' => $this->siteId
        ], $data));
    }

    public function test_get_for_collaboratable_returns_all_collaborators(): void
    {
        // Arrange
        $page = $this->createPage();
        $user1 = $this->createUser(['email' => 'user1@test.com']);
        $user2 = $this->createUser(['email' => 'user2@test.com']);

        $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user1->id,
            'role' => 'editor',
            'site_id' => $this->siteId
        ], $this->siteId);

        $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user2->id,
            'role' => 'viewer',
            'site_id' => $this->siteId
        ], $this->siteId);

        // Act
        $collaborators = $this->repository->getForCollaboratable(Page::class, $page->id);

        // Assert
        $this->assertCount(2, $collaborators);
    }

    public function test_find_by_collaboratable_and_user_returns_specific_collaborator(): void
    {
        // Arrange
        $page = $this->createPage();
        $user = $this->createUser();

        $created = $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user->id,
            'role' => 'editor',
            'site_id' => $this->siteId
        ], $this->siteId);

        // Act
        $found = $this->repository->findByCollaboratableAndUser(Page::class, $page->id, $user->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_remove_for_user_deletes_collaborator(): void
    {
        // Arrange
        $page = $this->createPage();
        $user = $this->createUser();

        $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user->id,
            'role' => 'editor',
            'site_id' => $this->siteId
        ], $this->siteId);

        // Act
        $result = $this->repository->removeForUser($page->id, $user->id, Page::class);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(
            $this->repository->findByCollaboratableAndUser(Page::class, $page->id, $user->id)
        );
    }

    public function test_page_collaborator_repository_works_correctly(): void
    {
        // Arrange
        $page = $this->createPage();
        $user = $this->createUser();

        // Act
        $collaborator = $this->pageCollaboratorRepository->createForPage($page->id, [
            'user_id' => $user->id,
            'role' => 'editor',
            'site_id' => $this->siteId
        ]);

        // Assert
        $this->assertNotNull($collaborator->id);

        $found = $this->pageCollaboratorRepository->getForPage($page->id);
        $this->assertCount(1, $found);
    }

    public function test_collaborators_are_isolated_between_types(): void
    {
        // Arrange
        $page = $this->createPage();
        $brief = $this->createBrief();
        $user = $this->createUser();

        $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user->id,
            'role' => 'editor',
            'site_id' => $this->siteId
        ], $this->siteId);

        $this->repository->createForCollaboratable(Brief::class, $brief->id, [
            'user_id' => $user->id,
            'role' => 'viewer',
            'site_id' => $this->siteId
        ], $this->siteId);

        // Act
        $pageCollaborators = $this->repository->getForCollaboratable(Page::class, $page->id);
        $briefCollaborators = $this->repository->getForCollaboratable(Brief::class, $brief->id);

        // Assert
        $this->assertCount(1, $pageCollaborators);
        $this->assertCount(1, $briefCollaborators);
        $this->assertEquals('editor', $pageCollaborators->first()->role);
        $this->assertEquals('viewer', $briefCollaborators->first()->role);
    }

    public function test_unique_constraint_prevents_duplicate_collaborators(): void
    {
        // Arrange
        $page = $this->createPage();
        $user = $this->createUser();

        $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user->id,
            'role' => 'editor',
            'site_id' => $this->siteId
        ], $this->siteId);

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->repository->createForCollaboratable(Page::class, $page->id, [
            'user_id' => $user->id,
            'role' => 'viewer',
            'site_id' => $this->siteId
        ], $this->siteId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CollaboratorRepository();
        $this->pageCollaboratorRepository = new PageCollaboratorRepository();
    }
}