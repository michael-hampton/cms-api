<?php

namespace App\Repositories\OpenCollab;

use App\Models\OpenCollabDocument;
use App\Repositories\Repository;

class OpenCollabDocumentRepository extends Repository
{
    public function findForSiteOrGlobal(int $id, int $siteId): ?OpenCollabDocument
    {
        $document = OpenCollabDocument::where('id', $id)->first();

        if (!$document) {
            return null;
        }

        if ($document->site_id !== null && (int)$document->site_id !== $siteId) {
            return null;
        }

        return $document;
    }

    protected function getModelClass(): string
    {
        return OpenCollabDocument::class;
    }
}
