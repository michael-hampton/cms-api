<?php

namespace App\Repositories\Cms;

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

    public function create(array $data): Site
    {
        $site = new Site($data);
        $site->save();
        return $site;
    }

    public function delete(int $id): bool
    {
        $site = $this->find($id);

        if (!$site) {
            throw new \Exception("Site not found");
        }

        return $site->delete();
    }

    public function findAll(): array
    {
        return Site::all()->toArray();
    }

    public function findActive(): array
    {
        return Site::active()->get()->toArray();
    }

    public function findDefault(): ?Site
    {
        return Site::default()->first();
    }

    public function existsByDomain(string $domain, ?int $excludeId = null): bool
    {
        $query = Site::where('domain', $domain);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function existsBySlug(string $slug, ?int $excludeId = null): bool
    {
        $query = Site::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}