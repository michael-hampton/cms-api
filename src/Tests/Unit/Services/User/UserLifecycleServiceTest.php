<?php

namespace App\Tests\Unit\Services\User;

use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Services\User\UserLifecycleService;
use Mockery;
use PHPUnit\Framework\TestCase;

final class UserLifecycleServiceTest extends TestCase
{
    public function test_find_by_email_normalises_before_lookup(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $user = $this->makeUser(['email' => 'guest@example.com']);

        $repository->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn($user);

        $this->assertSame($user, (new UserLifecycleService($repository))->findByEmail(' GUEST@example.com '));
    }

    public function test_find_by_id_delegates_to_user_repository(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $user = $this->makeUser(['id' => 7]);

        $repository->shouldReceive('find')
            ->with(7)
            ->once()
            ->andReturn($user);

        $this->assertSame($user, (new UserLifecycleService($repository))->findById(7));
    }

    public function test_ensure_contributor_account_creates_user_when_email_is_new(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $user = $this->makeUser(['id' => 10, 'email' => 'guest@example.com']);

        $repository->shouldReceive('findByEmail')
            ->with('guest@example.com')
            ->once()
            ->andReturn(null);

        $repository->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data): bool =>
                $data['email'] === 'guest@example.com'
                && $data['name'] === 'Jane'
                && $data['password'] === 'secret'
                && $data['role'] === 'contributor'
                && $data['is_contributor'] === true
                && $data['is_active'] === true
            )
            ->andReturn($user);

        $actual = (new UserLifecycleService($repository))->ensureContributorAccount(
            ' GUEST@example.com ',
            'Jane',
            'secret',
            reason: 'invited',
        );

        $this->assertSame($user, $actual);
    }

    public function test_ensure_contributor_account_reactivates_existing_user_without_password_or_name_mutation(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $existing = $this->makeUser(['id' => 7, 'name' => 'Existing']);
        $updated = $this->makeUser(['id' => 7, 'name' => 'Existing', 'is_active' => true]);

        $repository->shouldReceive('findByEmail')
            ->with('existing@example.com')
            ->once()
            ->andReturn($existing);

        $repository->shouldReceive('update')
            ->with(7, [
                'is_active' => true,
                'is_contributor' => true,
            ])
            ->once()
            ->andReturn($updated);

        $repository->shouldNotReceive('create');

        $actual = (new UserLifecycleService($repository))->ensureContributorAccount(
            'existing@example.com',
            'New Name',
            'new-password',
            reason: 'invited',
        );

        $this->assertSame($updated, $actual);
    }

    public function test_deactivate_contributor_delegates_account_state_to_user_repository(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $user = $this->makeUser(['id' => 7, 'is_active' => false]);

        $repository->shouldReceive('update')
            ->with(7, ['is_active' => false])
            ->once()
            ->andReturn($user);

        $this->assertSame($user, (new UserLifecycleService($repository))->deactivateContributor(7, 99, 'closed'));
    }

    public function test_reactivate_contributor_marks_account_active_and_contributor(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $user = $this->makeUser(['id' => 7, 'is_active' => true]);

        $repository->shouldReceive('update')
            ->with(7, [
                'is_active' => true,
                'is_contributor' => true,
            ])
            ->once()
            ->andReturn($user);

        $this->assertSame($user, (new UserLifecycleService($repository))->reactivateContributor(7, 99, 'reopened'));
    }

    public function test_change_contributor_role_delegates_role_change_to_user_repository(): void
    {
        $repository = Mockery::mock(UserRepositoryInterface::class);
        $user = $this->makeUser(['id' => 7, 'role' => 'author']);

        $repository->shouldReceive('update')
            ->with(7, ['role' => 'author'])
            ->once()
            ->andReturn($user);

        $this->assertSame($user, (new UserLifecycleService($repository))->changeContributorRole(7, 'author', 99, 'promotion'));
    }

    private function makeUser(array $attributes = []): User
    {
        $user = new User(array_merge([
            'id' => 1,
            'name' => 'Jane',
            'email' => 'existing@example.com',
            'role' => 'contributor',
            'is_active' => true,
            'is_contributor' => true,
        ], $attributes));
        $user->exists = true;

        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
