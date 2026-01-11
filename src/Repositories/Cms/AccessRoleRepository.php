<?php

namespace App\Repositories\Cms;

use App\Models\PageAccessRole;
use App\Repositories\Repository;

class AccessRoleRepository extends Repository
{
    public function __construct()
    {
        $this->withoutSiteFilter();
        parent::__construct();
    }


    protected function getModelClass(): string
    {
        return PageAccessRole::class;
    }

    public function syncAccessRoles(int $pageId, array $roleNames)
    {
        $existingRoles = PageAccessRole::where('page_id', $pageId)->get();

        // Delete existing roles
        $this->database->delete('page_access_roles', ['page_id' => $pageId]);


        // Process new tags
        foreach ($roleNames as $roleName) {
            if (!empty(trim($roleName))) {
                // Create page-tag relationship
                $this->create([
                    'page_id' => $pageId,
                    'role' => $roleName,
                ]);
            }
        }
    }
}