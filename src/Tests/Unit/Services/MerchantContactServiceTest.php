<?php

namespace App\Tests\Unit\Services;

use App\Models\MerchantContact;
use App\Repositories\Product\MerchantContactRepository;
use App\Services\Product\MerchantContactService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class MerchantContactServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected $repository;
    protected MerchantContactService $service;

    public function testGetAllContacts()
    {
        $contacts = collect([
            new MerchantContact(['id' => 1, 'name' => 'Contact 1']),
            new MerchantContact(['id' => 2, 'name' => 'Contact 2']),
        ]);

        $this->repository->shouldReceive('all')
            ->once()
            ->andReturn($contacts);

        $result = $this->service->getAllContacts();

        $this->assertCount(2, $result);
    }

    public function testCreateContact()
    {
        $data = [
            'merchant_id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $contact = new MerchantContact(array_merge(['id' => 1], $data));

        $this->repository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($contact);

        $result = $this->service->createContact($data);

        $this->assertEquals('John Doe', $result->name);
    }

    public function testUpdateContact()
    {
        $contact = new MerchantContact(['id' => 1, 'name' => 'Old Name']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($contact);

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($contact);

        $result = $this->service->updateContact(1, ['name' => 'New Name']);

        $this->assertNotNull($result);
    }

    public function testUpdateContactReturnsNullWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->updateContact(999, ['name' => 'Test']);

        $this->assertNull($result);
    }

    public function testDeleteContact()
    {
        $contact = new MerchantContact(['id' => 1]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($contact);

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteContact(1);

        $this->assertTrue($result);
    }

    public function testDeleteContactReturnsFalseWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->deleteContact(999);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(MerchantContactRepository::class);
        $this->service = new MerchantContactService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}