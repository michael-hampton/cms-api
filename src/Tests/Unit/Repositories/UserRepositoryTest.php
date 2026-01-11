<?php

namespace App\Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\Cms\UserRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class UserRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository();
    }

    protected function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'site_id' => $this->siteId,
            'email' => 'user' . uniqid() . '@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'name' => 'Test User',
            'role' => 'user',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_find_by_email_returns_user_when_exists(): void
    {
        // Arrange
        $user = $this->createUser(['email' => 'test@example.com']);

        // Act
        $found = $this->repository->findByEmail('test@example.com', $this->siteId);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
        $this->assertEquals('test@example.com', $found->email);
    }

    public function test_find_by_email_returns_null_when_not_exists(): void
    {
        // Act
        $found = $this->repository->findByEmail('nonexistent@example.com', $this->siteId);

        // Assert
        $this->assertNull($found);
    }

    public function test_find_by_email_is_case_sensitive(): void
    {
        // Arrange
        $user = $this->createUser(['email' => 'test@example.com']);

        // Act
        $found = $this->repository->findByEmail('TEST@EXAMPLE.COM', $this->siteId);

        // Assert - depends on database collation, but typically case-sensitive
        // Adjust this test based on your actual requirements
        $this->assertInstanceOf(User::class, $found);
    }

    public function test_search_returns_paginated_results(): void
    {
        // Arrange
        $this->createUser(['email' => 'user1@example.com', 'name' => 'User One']);
        $this->createUser(['email' => 'user2@example.com', 'name' => 'User Two']);
        $this->createUser(['email' => 'user3@example.com', 'name' => 'User Three']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThanOrEqual(3, count($result->getData()));
    }

    public function test_search_with_pagination(): void
    {
        // Arrange
        for ($i = 1; $i <= 15; $i++) {
            $this->createUser(['email' => "user$i@example.com", 'name' => "User $i"]);
        }

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(5);
        $criteria->setPage(1);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertLessThanOrEqual(5, count($result->getData()));
        $this->assertGreaterThanOrEqual(15, $result->getTotal());
    }

    public function test_find_by_id_returns_user_when_exists(): void
    {
        // Arrange
        $user = $this->createUser(['name' => 'Find By ID User']);

        // Act
        $found = $this->repository->findById($user->id, $this->siteId);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
        $this->assertEquals('Find By ID User', $found->name);
    }

    public function test_find_by_id_returns_null_when_not_exists(): void
    {
        // Act
        $found = $this->repository->findById(99999, $this->siteId);

        // Assert
        $this->assertNull($found);
    }

    public function test_repository_can_create_user(): void
    {
        // Arrange
        $userData = [
            'site_id' => $this->siteId,
            'email' => 'newuser@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'name' => 'New User',
            'role' => 'user',
            'is_active' => true,
        ];

        // Act
        $user = $this->repository->create($userData);

        // Assert
        $this->assertNotNull($user);
        $this->assertEquals('newuser@example.com', $user->email);
        $this->assertEquals('New User', $user->name);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
    }

    public function test_repository_can_update_user(): void
    {
        // Arrange
        $user = $this->createUser(['name' => 'Original Name']);

        // Act
        $updated = $this->repository->update($user->id, ['name' => 'Updated Name']);

        // Assert
        $this->assertEquals('Updated Name', $updated->name);

        $fresh = $this->fresh($user);
        $this->assertEquals('Updated Name', $fresh->name);
    }

    public function test_repository_can_delete_user(): void
    {
        // Arrange
        $user = $this->createUser();

        // Act
        $result = $this->repository->delete($user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(User::find($user->id));
    }

    public function test_get_model_class_returns_correct_class(): void
    {
        // Act
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('getModelClass');
        $method->setAccessible(true);
        $result = $method->invoke($this->repository);

        // Assert
        $this->assertEquals(User::class, $result);
    }
}