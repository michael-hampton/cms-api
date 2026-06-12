<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\UserSite;
use App\Repositories\OpenCollab\OpenCollabDocumentRepository;
use App\Services\OpenCollab\OpenCollabDocumentService;

class OpenCollabDocumentController extends Controller
{
    public function __construct(
        private readonly OpenCollabDocumentRepository $documents,
        private readonly OpenCollabDocumentService $documentService,
    ) {
        parent::__construct();
    }

    public function preview(int $id): Response
    {
        return $this->serve($id, 'inline');
    }

    public function download(int $id): Response
    {
        return $this->serve($id, 'attachment');
    }

    private function serve(int $id, string $disposition): Response
    {
        $siteId = SiteContext::getId();

        if (!$this->userCanAccessSite($siteId)) {
            return Response::json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $document = $this->documents->findForSiteOrGlobal($id, $siteId);

        if (!$document) {
            return Response::json(['success' => false, 'message' => 'Document not found'], 404);
        }

        $path = $this->documentService->absolutePath($document->path);

        if (!is_file($path)) {
            return Response::json(['success' => false, 'message' => 'Document file not found'], 404);
        }

        $filename = addcslashes($document->original_filename ?: $document->stored_filename, "\\\"");
        $content = file_get_contents($path);

        if ($content === false) {
            return Response::json(['success' => false, 'message' => 'Document file could not be read'], 500);
        }

        return new Response($content, 200, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Content-Length' => (string)strlen($content),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function userCanAccessSite(int $siteId): bool
    {
        $userId = Auth::id();

        if (!$userId) {
            return false;
        }

        return UserSite::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->exists();
    }
}
