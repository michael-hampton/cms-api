<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\User;
use App\Requests\CreateSiteRequest;
use App\Requests\UpdateContactInfoRequest;
use App\Requests\UpdateSiteRequest;
use App\Requests\UpdateSocialMediaRequest;
use App\Repositories\OpenCollab\RbacRepository;
use App\Services\Cms\SiteService;

class SiteController extends Controller
{
    private SiteService $siteService;

    public function __construct(
        SiteService $siteService,
        private AuthenticationService $authService,
        private RbacRepository $rbacRepository
    ) {
        $this->siteService = $siteService;

        parent::__construct();
    }

    public function index(Request $request)
    {
        try {
            $userId = $this->authenticatedUserId($request);

            if ($userId !== null) {
                $siteIds = array_map(
                    fn(array $assignment) => (int) $assignment['site_id'],
                    $this->rbacRepository->activeSiteAssignmentsForUser($userId)
                );

                return $this->jsonResponse(
                    $siteIds === []
                        ? []
                        : Site::whereIn('id', $siteIds)->where('is_active', 1)->orderBy('name')->get()->toArray()
                );
            }

            $sites = $this->siteService->getAllSites();
            return $this->jsonResponse($sites);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $id)
    {
        try {
            $site = $this->siteService->getSiteById($id);

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            return $this->jsonResponse($site);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getCurrent()
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            return $this->jsonResponse($site->toArray());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function create(CreateSiteRequest $request)
    {
        try {
            $data = $request->validated();
            $site = $this->siteService->createSite($data);

            return $this->jsonResponse($site, 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(UpdateSiteRequest $request, int $id)
    {
        try {
            $data = $request->validated();
            $site = $this->siteService->updateSite($id, $data);

            return $this->jsonResponse($site);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateCurrent(UpdateSiteRequest $request)
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            $data = $request->validated();
            $updatedSite = $this->siteService->updateSite($site->id, $data);

            return $this->jsonResponse($updatedSite);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function delete(int $id)
    {
        try {
            $this->siteService->deleteSite($id);
            return $this->jsonResponse(['message' => 'Site deleted successfully']);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getContactInfo()
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            return $this->jsonResponse($site->getContactInfo());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateContactInfo(UpdateContactInfoRequest $request)
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            $contactData = $request->validated();
            $updatedSite = $this->siteService->updateContactInfo($site->id, $contactData);

            return $this->jsonResponse($updatedSite);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateSocialMedia(UpdateSocialMediaRequest $request)
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            $socialData = $request->validated();
            $updatedSite = $this->siteService->updateSocialMedia($site->id, $socialData);

            return $this->jsonResponse($updatedSite);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function uploadLogo(Request $request)
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            $file = $request->file('logo');

            if (!$file) {
                return $this->errorResponse('No logo file uploaded', 422);
            }

            if (!$file->isValid()) {
                return $this->errorResponse('Invalid file upload', 422);
            }

            // Validate file type
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->errorResponse('Invalid file type. Only images are allowed.', 422);
            }

            // Validate file size (2MB)
            if ($file->getSize() > 2 * 1024 * 1024) {
                return $this->errorResponse('File size exceeds 2MB limit', 422);
            }

            $uploadDir = realpath(__DIR__ . '/../../uploads/logos');
            if (!$uploadDir) {
                mkdir(__DIR__ . '/../../uploads/logos', 0777, true);
                $uploadDir = realpath(__DIR__ . '/../../uploads/logos');
            }

            $filename = 'logo_' . $site->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filepath = $uploadDir . '/' . $filename;

            if ($_ENV['APP_ENV'] !== 'testing') {
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (!$file->moveTo($filepath)) {
                    return $this->errorResponse('Failed to upload logo', 500);
                }
            }

            $logoPath = '/uploads/logos/' . $filename;
            $updatedSite = $this->siteService->updateLogo($site->id, $logoPath);

            return $this->jsonResponse($updatedSite);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function uploadFavicon(Request $request)
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            $file = $request->file('favicon');

            if (!$file) {
                return $this->errorResponse('No favicon file uploaded', 422);
            }

            if (!$file->isValid()) {
                return $this->errorResponse('Invalid file upload', 422);
            }

            // Validate file type
            $allowedMimes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->errorResponse('Invalid file type. Only .ico or .png files are allowed.', 422);
            }

            // Validate file size (100KB)
            if ($file->getSize() > 100 * 1024) {
                return $this->errorResponse('File size exceeds 100KB limit', 422);
            }

            $uploadDir = realpath(__DIR__ . '/../../uploads/favicons');
            if (!$uploadDir) {
                mkdir(__DIR__ . '/../../uploads/favicons', 0777, true);
                $uploadDir = realpath(__DIR__ . '/../../uploads/favicons');
            }

            $filename = 'favicon_' . $site->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filepath = $uploadDir . '/' . $filename;

            if ($_ENV['APP_ENV'] !== 'testing') {
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (!$file->moveTo($filepath)) {
                    return $this->errorResponse('Failed to upload favicon', 500);
                }
            }

            $faviconPath = '/uploads/favicons/' . $filename;
            $updatedSite = $this->siteService->updateFavicon($site->id, $faviconPath);

            return $this->jsonResponse($updatedSite);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateSettings(Request $request)
    {
        try {
            $site = SiteContext::get();

            if (!$site) {
                return $this->errorResponse('Site not found', 404);
            }

            $settings = $request->all();
            $updatedSite = $this->siteService->updateSettings($site->id, $settings);

            return $this->jsonResponse($updatedSite);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function toggleStatus(Request $request, int $id)
    {
        try {
            $isActive = $request->input('is_active', true);
            $updatedSite = $this->siteService->toggleStatus($id, $isActive);

            return $this->jsonResponse($updatedSite);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function authenticatedUserId(Request $request): ?int
    {
        $header = $request->header('Authorization', '');

        if (!preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return null;
        }

        $token = $this->authService->validateAccessToken($matches[1], SiteContext::getId() ?? 1);

        if (!$token || $token->getTokenableType() !== User::class) {
            return null;
        }

        return $token->getTokenableId();
    }
}
