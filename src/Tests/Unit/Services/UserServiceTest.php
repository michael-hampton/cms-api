<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Hash;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\UserService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends FunctionalTestCase
{
    protected UserRepositoryInterface $repository;
    protected UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(UserRepository::class);
        $this->service = new UserService($this->repository);
    }

    public function testSearchUsersReturnsResults(): void
    {
        $criteria = new SearchCriteria(
            filters: ['role' => 'admin'],
            page: 1,
            perPage: 10
        );

        $paginatedResult = new PaginatedResult(
            [['id' => 1, 'name' => 'Admin User', 'role' => 'admin']],
            1,
            1,
            10
        );

        $this->repository
            ->shouldReceive('search')
            ->once()
            ->with(Mockery::type(SearchCriteria::class))
            ->andReturn($paginatedResult);

        $result = $this->service->searchUsers($criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertEquals(1, $result->getTotal());
    }

    public function testGetUserByIdReturnsUser(): void
    {
        $user = new User(['id' => 1, 'name' => 'John Doe']);

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($user);

        $result = $this->service->getUserById(1);

        $this->assertSame($user, $result);
    }

    public function testGetUserByIdReturnsNullWhenNotFound(): void
    {
        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $result = $this->service->getUserById(999);

        $this->assertNull($result);
    }

    public function testCreateUserHashesPassword(): void
    {
        $hashMock = Mockery::mock('overload:App\Framework\Support\Hash');

        $hashMock->shouldReceive('make')
            ->once()
            ->with('password123')
            ->andReturn('hashed_password');

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        $user = new User($data);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['password'] === 'hashed_password';
            }))
            ->andReturn($user);

        $result = $this->service->createUser($data);

        $this->assertInstanceOf(User::class, $result);
    }

    public function test_update_user_hashes_password_when_provided(): void
    {
        $hashMock = Mockery::mock('overload:App\Framework\Support\Hash');

        $hashMock->shouldReceive('make')
            ->once()
            ->with('newpassword')
            ->andReturn('hashed_new_password');

        $data = [
            'name' => 'Jane Doe',
            'password' => 'newpassword',
        ];

        $user = new User(['id' => 1, 'name' => 'Jane Doe', 'password' => 'newpassword']);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return $arg['password'] === 'hashed_new_password';
            }))
            ->andReturn($user);

        $result = $this->service->updateUser(1, $data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Jane Doe', $result->name);
    }

    public function testUpdateUserRemovesPasswordWhenNotProvided(): void
    {
        $data = ['name' => 'Jane Doe'];
        $user = new User(['id' => 1, 'name' => 'John Doe', 'password' => '<PASSWORD>']);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return !isset($arg['password']);
            }))
            ->andReturn($user);

        $result = $this->service->updateUser(1, $data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('John Doe', $result->name);
    }

    public function testDeleteUserReturnsTrueOnSuccess(): void
    {
        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteUser(1);

        $this->assertTrue($result);
    }

    public function testDeleteUserReturnsFalseOnFailure(): void
    {
        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(999)
            ->andReturn(false);

        $result = $this->service->deleteUser(999);

        $this->assertFalse($result);
    }

    public function testIsEmailTakenReturnsTrueWhenEmailExists(): void
    {
        $user = new User(['id' => 1, 'email' => 'john@example.com', 'site_id' => $this->siteId]);

        $this->repository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('john@example.com', $this->siteId)
            ->andReturn($user);

        $result = $this->service->isEmailTaken('john@example.com', $this->siteId);

        $this->assertTrue($result);
    }

    public function testIsEmailTakenReturnsFalseWhenEmailNotExists(): void
    {
        $this->repository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('notfound@example.com', $this->siteId)
            ->andReturn(null);

        $result = $this->service->isEmailTaken('notfound@example.com', $this->siteId);;

        $this->assertFalse($result);
    }

    public function testIsEmailTakenExcludesSpecifiedUserId(): void
    {
        $user = new User(['id' => 1, 'email' => 'john@example.com', 'site_id' => $this->siteId]);;

        $this->repository
            ->shouldReceive('findByEmail')
            ->once()
            ->with('john@example.com', $this->siteId)
            ->andReturn($user);

        $result = $this->service->isEmailTaken('john@example.com', $this->siteId, 1);;

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}