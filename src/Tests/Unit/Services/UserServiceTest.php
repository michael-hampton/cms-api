<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Hash;
use App\Models\User;
use App\Repositories\Cms\UserRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Services\Cms\UserService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

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

        $expectedData = [
            ['id' => 1, 'name' => 'Admin User', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Another Admin', 'role' => 'admin']
        ];

        $paginatedResult = new PaginatedResult(
            $expectedData,
            2,
            1,
            10
        );

        $this->repository
            ->shouldReceive('search')
            ->once()
            ->with(Mockery::on(function ($arg) use ($criteria) {
                return $arg instanceof SearchCriteria
                    && $arg->getFilters() === $criteria->getFilters()
                    && $arg->getPage() === $criteria->getPage()
                    && $arg->getPerPage() === $criteria->getPerPage();
            }))
            ->andReturn($paginatedResult);

        $result = $this->service->searchUsers($criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertSame(2, $result->getTotal());
        $this->assertSame($expectedData, $result->getData());
        $this->assertSame(1, $result->getPage());
        $this->assertSame(10, $result->getPerPage());
    }

    public function testSearchUsersWithEmptyResults(): void
    {
        $criteria = new SearchCriteria(
            filters: ['role' => 'nonexistent'],
            page: 1,
            perPage: 10
        );

        $paginatedResult = new PaginatedResult([], 0, 1, 10);

        $this->repository
            ->shouldReceive('search')
            ->once()
            ->with(Mockery::type(SearchCriteria::class))
            ->andReturn($paginatedResult);

        $result = $this->service->searchUsers($criteria);

        $this->assertSame(0, $result->getTotal());
        $this->assertEmpty($result->getData());
    }

    public function testGetUserByIdReturnsUser(): void
    {
        $expectedUser = new User([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($expectedUser);

        $result = $this->service->getUserById(1);

        $this->assertSame($expectedUser, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('John Doe', $result->name);
        $this->assertSame('john@example.com', $result->email);
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

    #[DataProvider('invalidUserIdProvider')]
    public function testGetUserByIdWithInvalidIds($invalidId): void
    {
        $this->repository
            ->shouldReceive('find')
            ->once()
            ->with($invalidId)
            ->andReturn(null);

        $result = $this->service->getUserById($invalidId);

        $this->assertNull($result);
    }

    public static function invalidUserIdProvider(): array
    {
        return [
            'negative id' => [-1],
            'zero id' => [0],
            'very large id' => [PHP_INT_MAX],
        ];
    }

    public function testCreateUserHashesPassword(): void
    {
        $plainPassword = 'password123';
        $hashedPassword = 'hashed_password_' . bin2hex(random_bytes(16));

        $hashMock = Mockery::mock('overload:' . Hash::class);
        $hashMock->shouldReceive('make')
            ->once()
            ->with($plainPassword)
            ->andReturn($hashedPassword);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => $plainPassword,
            'role' => 'user'
        ];

        $expectedUser = new User(array_merge($data, ['password' => $hashedPassword]));

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($data, $hashedPassword) {
                return is_array($arg)
                    && $arg['name'] === $data['name']
                    && $arg['email'] === $data['email']
                    && $arg['password'] === $hashedPassword
                    && $arg['role'] === $data['role'];
            }))
            ->andReturn($expectedUser);

        $result = $this->service->createUser($data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('John Doe', $result->name);
        $this->assertSame('john@example.com', $result->email);
    }

    public function testCreateUserWithoutOptionalFields(): void
    {
        $plainPassword = 'password123';
        $hashedPassword = 'hashed_' . bin2hex(random_bytes(8));

        $hashMock = Mockery::mock('overload:' . Hash::class);
        $hashMock->shouldReceive('make')
            ->once()
            ->with($plainPassword)
            ->andReturn($hashedPassword);

        $data = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => $plainPassword
        ];

        $expectedUser = new User(array_merge($data, ['password' => $hashedPassword]));

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) use ($hashedPassword) {
                return $arg['password'] === $hashedPassword;
            }))
            ->andReturn($expectedUser);

        $result = $this->service->createUser($data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('Jane Smith', $result->name);
    }


    public function testUpdateUserHashesPasswordWhenProvided(): void
    {
        $newPassword = 'newpassword123';
        $hashedPassword = 'hashed_new_' . bin2hex(random_bytes(16));

        $hashMock = Mockery::mock('overload:' . Hash::class);
        $hashMock->shouldReceive('make')
            ->once()
            ->with($newPassword)
            ->andReturn($hashedPassword);

        $data = [
            'name' => 'Jane Doe',
            'password' => $newPassword,
            'email' => 'jane.doe@example.com'
        ];

        $updatedUser = new User([
            'id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'password' => $hashedPassword
        ]);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) use ($hashedPassword) {
                return is_array($arg)
                    && $arg['password'] === $hashedPassword
                    && $arg['name'] === 'Jane Doe'
                    && $arg['email'] === 'jane.doe@example.com';
            }))
            ->andReturn($updatedUser);

        $result = $this->service->updateUser(1, $data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame('Jane Doe', $result->name);
        $this->assertSame('jane.doe@example.com', $result->email);
    }


    public function testUpdateUserRemovesPasswordWhenNotProvided(): void
    {
        $data = [
            'name' => 'Jane Doe Updated',
            'email' => 'jane.updated@example.com'
        ];

        $updatedUser = new User([
            'id' => 1,
            'name' => 'Jane Doe Updated',
            'email' => 'jane.updated@example.com',
            'password' => 'existing_hashed_password'
        ]);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) use ($data) {
                return is_array($arg)
                    && !array_key_exists('password', $arg)
                    && $arg['name'] === $data['name']
                    && $arg['email'] === $data['email'];
            }))
            ->andReturn($updatedUser);

        $result = $this->service->updateUser(1, $data);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Jane Doe Updated', $result->name);
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