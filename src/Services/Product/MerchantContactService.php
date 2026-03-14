<?php

namespace App\Services\Product;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Repositories\Product\MerchantContactRepository;

class MerchantContactService
{
    protected MerchantContactRepository $repository;

    public function __construct(MerchantContactRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAllContacts(): Collection
    {
        return $this->repository->all();
    }

    public function getContact(int $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function createContact(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function updateContact(int $id, array $data): ?Model
    {
        $contact = $this->repository->find($id);

        if (!$contact) {
            return null;
        }

        return $this->repository->update($id, $data);
    }

    public function deleteContact(int $id): bool
    {
        $contact = $this->repository->find($id);

        if (!$contact) {
            return false;
        }

        return $this->repository->delete($id);
    }

    public function getContactsByMerchant(int $merchantId): Collection
    {
        return $this->repository->getByMerchant($merchantId);
    }

    public function searchContacts(\App\Search\SearchCriteria $criteria)
    {
    }
}