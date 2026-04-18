<?php

namespace App\Tests\Unit\Services\Members;

use App\Framework\Database\Database;
use App\Models\ConsentType;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Services\Members\ConsentTypeAdminService;
use Mockery;
use PHPUnit\Framework\TestCase;

class ConsentTypeAdminServiceTest extends TestCase
{
    private ConsentTypeRepository $repository;
    private Database $databaseMock;
    private ConsentTypeAdminService $service;

    public function test_create_persists_consent_type(): void
    {
        $payload = $this->payload();
        $consentType = $this->makeConsentType(1);

        $this->repository->allows('existsByCode')->with('marketing_email')->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $callback) => $callback());
        $this->repository->expects('create')
            ->withArgs(fn(array $data) => $data['code'] === 'marketing_email' && $data['required'] === false)
            ->andReturn($consentType);

        $result = $this->service->create($payload);

        $this->assertSame($consentType, $result);
    }

    private function payload(): array
    {
        return [
            'code' => 'marketing_email',
            'name' => 'Marketing Email',
            'category' => 'marketing',
            'required' => false,
            'data_purposes' => ['email'],
        ];
    }

    private function makeConsentType(int $id, string $code = 'marketing_email'): ConsentType
    {
        $consentType = Mockery::mock(ConsentType::class)->makePartial();
        $consentType->id = $id;
        $consentType->code = $code;
        $consentType->name = 'Test';
        return $consentType;
    }

    public function test_create_throws_when_code_exists(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->allows('existsByCode')->andReturn(true);

        $this->service->create($this->payload());
    }

    public function test_update_persists_changes(): void
    {
        $existing = $this->makeConsentType(4);
        $updated = $this->makeConsentType(4, 'analytics');

        $this->repository->allows('find')->with(4)->andReturn($existing, $updated);
        $this->repository->allows('existsByCode')->with('analytics', 4)->andReturn(false);
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $callback) => $callback());
        $this->repository->expects('update')->once();

        $result = $this->service->update(4, ['code' => 'analytics']);

        $this->assertSame($updated, $result);
    }

    public function test_delete_throws_when_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository->allows('find')->with(99)->andReturn(null);

        $this->service->delete(99);
    }

    protected function setUp(): void
    {
        $this->repository = Mockery::mock(ConsentTypeRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->service = new ConsentTypeAdminService($this->repository, $this->databaseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
