<?php

namespace App\Tests\Unit\Repositories;

namespace App\Tests\Unit\Repositories;

use App\Repositories\Product\MerchantContactRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantContactRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MerchantContactRepository $repository;

    public function testGetByMerchantReturnsContactsForMerchant()
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->createMerchantContact(['merchant_id' => $merchant1->id]);
        $this->createMerchantContact(['merchant_id' => $merchant1->id]);
        $this->createMerchantContact(['merchant_id' => $merchant2->id]);

        $contacts = $this->repository->getByMerchant($merchant1->id);

        $this->assertCount(2, $contacts);
    }

    public function testFindByEmailReturnsContact()
    {
        $contact = $this->createMerchantContact(['email' => 'test@example.com']);

        $found = $this->repository->findByEmail('test@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($contact->id, $found->id);
    }

    public function testFindByEmailReturnsNullWhenNotFound()
    {
        $found = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($found);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MerchantContactRepository();
    }
}