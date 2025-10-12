<?php

namespace App\Repositories;

use App\Framework\Database\Database;
use App\Models\Model;
use App\Models\Site;

class SiteRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function find(int $id): ?Model
    {
        return Site::find($id);
    }

    public function findByDomain(string $domain): ?Site
    {
        return Site::where('domain', $domain)->first();
    }

    public function update(int $id, array $data): Site
    {
        $site = $this->find($id);
        if (!$site) {
            throw new \Exception("Site not found");
        }

        $site->fill($data);
        $site->save();

        return $site;
    }

    public function updateContactInfo(int $id, array $contactData): Site
    {
        return $this->update($id, $contactData);
    }
}