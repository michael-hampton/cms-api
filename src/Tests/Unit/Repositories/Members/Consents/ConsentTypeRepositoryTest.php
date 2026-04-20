<?php

namespace App\Tests\Unit\Repositories\Members\Consents;

use App\Models\ConsentType;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ConsentTypeRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ConsentTypeRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ConsentTypeRepository();
    }

    public function test_find_returns_consent_type(): void
    {
        $consentType = $this->createConsentType(['code' => 'marketing']);

        $result = $this->repository->find($consentType->id);

        $this->assertNotNull($result);
        $this->assertEquals($consentType->id, $result->id);
    }

    public function test_exists_by_code_returns_true_when_exists(): void
    {
        $this->createConsentType(['code' => 'marketing']);

        $this->assertTrue($this->repository->existsByCode('marketing'));
        $this->assertFalse($this->repository->existsByCode('non-existent'));
    }

    public function test_find_all_active_returns_only_active(): void
    {
        $this->createConsentType(['code' => 'active1', 'is_active' => true]);
        $this->createConsentType(['code' => 'inactive1', 'is_active' => false]);

        $result = $this->repository->findAllActive();

        $this->assertCount(1, $result);
        $this->assertEquals('active1', $result->first()->code);
    }

    public function test_create_persists_data(): void
    {
        $data = [
            'code' => 'new-code',
            'name' => 'New Consent',
            'category' => 'marketing',
            'is_active' => true,
            'description' => 'test description',
            'data_purposes' => json_encode(['email'])
        ];

        $consentType = $this->repository->create($data);

        $this->assertNotNull($consentType->id);
        $this->assertEquals('new-code', $consentType->code);
        $this->assertDatabaseHas('consent_types', ['code' => 'new-code']);
    }

    public function test_update_changes_data(): void
    {
        $consentType = $this->createConsentType(['name' => 'Old Name']);

        $updated = $this->repository->update($consentType->id, ['name' => 'New Name']);

        $this->assertNotNull($updated);
        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('consent_types', ['id' => $consentType->id, 'name' => 'New Name']);
    }

    public function test_delete_removes_record(): void
    {
        $consentType = $this->createConsentType();

        $result = $this->repository->delete($consentType->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('consent_types', ['id' => $consentType->id]);
    }
}
