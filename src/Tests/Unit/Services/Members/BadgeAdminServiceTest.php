<?php

namespace App\Tests\Unit\Services\Members;

use App\Framework\Database\Database;
use App\Models\Badge;
use App\Repositories\Members\BadgeRepository;
use App\Services\Members\BadgeService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

/**
 * Unit tests for BadgeService admin CRUD methods.
 *
 * All repository and databaseMock calls are mocked.
 * Existing engine tests live in BadgeServiceTest — this file covers only
 * the admin surface added in this ticket.
 */
class BadgeAdminServiceTest extends FunctionalTestCase
{
    private BadgeRepository $repository;
    private Database $databaseMock;
    private BadgeService $service;

    public function test_create_badge_persists_and_returns_badge(): void
    {
        $payload = $this->validPayload();
        $badge = $this->makeBadge(1);

        $this->repository->allows('existsByNameForSite')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->expects('create')
            ->withArgs(fn(array $d) => $d['name'] === 'Reader'
                && $d['site_id'] === 10
                && $d['criteria'] === $payload['criteria']
                && $d['points'] === 50
                && $d['is_active'] === true
            )
            ->andReturn($badge);

        $result = $this->service->createBadge($payload, 10);

        $this->assertInstanceOf(Badge::class, $result);
    }

    private function validPayload(): array
    {
        return [
            'name' => 'Reader',
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 10],
            ],
            'points' => 50,
            'is_active' => true,
        ];
    }

    // =========================================================================
    // createBadge
    // =========================================================================

    private function makeBadge(int $id, string $name = 'Reader'): Badge
    {
        $badge = new Badge();
        $badge->id = $id;
        $badge->name = $name;
        return $badge;
    }

    public function test_create_badge_wraps_write_in_transaction(): void
    {
        $payload = $this->validPayload();
        $badge = $this->makeBadge(1);

        $this->repository->allows('existsByNameForSite')->andReturn(false);
        $this->databaseMock->expects('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->allows('create')->andReturn($badge);

        $this->service->createBadge($payload, 10);

        $this->assertTrue(true);
    }

    public function test_create_badge_throws_when_name_is_duplicate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/i');

        $this->repository->allows('existsByNameForSite')->andReturn(true);

        $this->service->createBadge($this->validPayload(), 10);
    }

    public function test_create_badge_throws_when_criteria_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/criterion/i');

        $payload = $this->validPayload();
        $payload['criteria'] = [];

        $this->repository->allows('existsByNameForSite')->andReturn(false);

        $this->service->createBadge($payload, 10);
    }

    public function test_create_badge_throws_when_criteria_type_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/valid type/i');

        $payload = $this->validPayload();
        $payload['criteria'] = [['type' => 'INVALID_TYPE', 'operator' => '>=', 'value' => 5]];

        $this->repository->allows('existsByNameForSite')->andReturn(false);

        $this->service->createBadge($payload, 10);
    }

    public function test_create_badge_throws_when_criteria_operator_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/valid operator/i');

        $payload = $this->validPayload();
        $payload['criteria'] = [['type' => 'comments_count', 'operator' => '??', 'value' => 5]];

        $this->repository->allows('existsByNameForSite')->andReturn(false);

        $this->service->createBadge($payload, 10);
    }

    public function test_create_badge_throws_when_criteria_value_is_non_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/numeric/i');

        $payload = $this->validPayload();
        $payload['criteria'] = [['type' => 'comments_count', 'operator' => '>=', 'value' => 'lots']];

        $this->repository->allows('existsByNameForSite')->andReturn(false);

        $this->service->createBadge($payload, 10);
    }

    public function test_create_badge_trims_name_before_persisting(): void
    {
        $payload = $this->validPayload();
        $payload['name'] = '  Reader  ';
        $badge = $this->makeBadge(1);

        $this->repository->allows('existsByNameForSite')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->expects('create')
            ->withArgs(fn(array $d) => $d['name'] === 'Reader')
            ->andReturn($badge);

        $this->service->createBadge($payload, 10);

        $this->assertTrue(true);
    }

    // =========================================================================
    // updateBadge
    // =========================================================================

    public function test_update_badge_persists_changes_and_returns_refreshed_badge(): void
    {
        $existing = $this->makeBadge(5);
        $updated = $this->makeBadge(5, name: 'Super Reader');

        $this->repository->allows('findForSite')->with(5, 10)->andReturn($existing);
        $this->repository->allows('existsByNameForSite')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->expects('update')->once();
        $this->repository->allows('find')->with(5)->andReturn($updated);

        $result = $this->service->updateBadge(5, ['name' => 'Super Reader'], 10);

        $this->assertSame($updated, $result);
    }

    public function test_update_badge_wraps_write_in_transaction(): void
    {
        $existing = $this->makeBadge(5);
        $updated = $this->makeBadge(5);

        $this->repository->allows('findForSite')->andReturn($existing);
        $this->repository->allows('existsByNameForSite')->andReturn(false);
        $this->databaseMock->expects('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->allows('update');
        $this->repository->allows('find')->andReturn($updated);

        $this->service->updateBadge(5, ['name' => 'New Name'], 10);

        $this->assertTrue(true);
    }

    public function test_update_badge_throws_when_badge_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->repository->allows('findForSite')->andReturn(null);

        $this->service->updateBadge(99, ['name' => 'x'], 10);
    }

    public function test_update_badge_throws_on_duplicate_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/i');

        $existing = $this->makeBadge(5);
        $this->repository->allows('findForSite')->andReturn($existing);
        $this->repository->allows('existsByNameForSite')->andReturn(true);

        $this->service->updateBadge(5, ['name' => 'Taken Name'], 10);
    }

    public function test_update_badge_validates_new_criteria_when_provided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/valid type/i');

        $existing = $this->makeBadge(5);
        $this->repository->allows('findForSite')->andReturn($existing);
        $this->repository->allows('existsByNameForSite')->andReturn(false);

        $this->service->updateBadge(5, [
            'criteria' => [['type' => 'NOPE', 'operator' => '>=', 'value' => 1]],
        ], 10);
    }

    public function test_update_badge_does_not_validate_criteria_when_not_in_payload(): void
    {
        $existing = $this->makeBadge(5);
        $updated = $this->makeBadge(5);

        $this->repository->allows('findForSite')->andReturn($existing);
        $this->repository->allows('existsByNameForSite')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->allows('update');
        $this->repository->allows('find')->andReturn($updated);

        // No criteria key — should not throw
        $result = $this->service->updateBadge(5, ['is_active' => false], 10);

        $this->assertInstanceOf(Badge::class, $result);
    }

    // =========================================================================
    // deleteBadge
    // =========================================================================

    public function test_delete_badge_removes_badge(): void
    {
        $existing = $this->makeBadge(5);

        $this->repository->allows('findForSite')->with(5, 10)->andReturn($existing);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->expects('delete')->with(5)->once();

        $this->service->deleteBadge(5, 10);

        $this->assertTrue(true);
    }

    public function test_delete_badge_wraps_delete_in_transaction(): void
    {
        $existing = $this->makeBadge(5);

        $this->repository->allows('findForSite')->andReturn($existing);
        $this->databaseMock->expects('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());
        $this->repository->allows('delete');

        $this->service->deleteBadge(5, 10);

        $this->assertTrue(true);
    }

    public function test_delete_badge_throws_when_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->repository->allows('findForSite')->andReturn(null);

        $this->service->deleteBadge(99, 10);
    }

    // =========================================================================
    // listForSite / findForSite
    // =========================================================================

    public function test_list_for_site_delegates_to_repository(): void
    {
        $paginator = [];

        $this->repository->expects('paginate')->with(10, 20)->andReturn($paginator);

        $result = $this->service->listForSite(10);

        $this->assertSame($paginator, $result);
    }

    public function test_find_for_site_returns_badge_when_found(): void
    {
        $badge = $this->makeBadge(3);
        $this->repository->allows('findForSite')->with(3, 10)->andReturn($badge);

        $result = $this->service->findForSite(3, 10);

        $this->assertSame($badge, $result);
    }

    public function test_find_for_site_throws_when_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->repository->allows('findForSite')->andReturn(null);

        $this->service->findForSite(99, 10);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(BadgeRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new BadgeService($this->repository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}