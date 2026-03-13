<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterCreationScheduleRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
use App\Requests\Newsletter\CreateNewsletterScheduleRequest;
use App\Requests\Newsletter\UpdateNewsletterScheduleRequest;
use App\Resources\NewsletterSendScheduleResource;
use App\Services\Newsletter\NewsletterScheduleService;
use DomainException;

class NewsletterScheduleController extends Controller
{
    public function __construct(
        private readonly NewsletterScheduleService            $scheduleService,
        private readonly NewsletterCreationScheduleRepository $creationRepo,
        private readonly NewsletterSendScheduleRepository     $sendRepo,
    )
    {
        parent::__construct();
    }

    // =========================================================================
    // GET /newsletters/{newsletterId}/schedules
    // =========================================================================

    public function index(int $newsletterId): JsonResponse
    {
        $creation = $this->creationRepo->findByNewsletterId($newsletterId);
        $send = $this->sendRepo->findByNewsletterId($newsletterId);

        return $this->resourceResponse([
            'schedules' => [
                // TODO: Replace ->toArray() with NewsletterCreationScheduleResource once model is available
                'creation' => $creation?->toArray(),
                'send' => $send ? NewsletterSendScheduleResource::make($send)->toArray() : null,
            ],
        ]);
    }

    // =========================================================================
    // Creation Schedule
    // =========================================================================

    public function storeCreation(CreateNewsletterScheduleRequest $request, int $newsletterId): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->createCreationSchedule(
                $newsletterId,
                SiteContext::getId(),
                $request->validated()
            );

            // TODO: Replace ->toArray() with NewsletterCreationScheduleResource once model is available
            return $this->resourceResponse(['schedule' => $schedule->toArray()], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateCreation(UpdateNewsletterScheduleRequest $request, int $newsletterId, int $scheduleId): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->updateCreationSchedule($scheduleId, $request->validated());

            // TODO: Replace ->toArray() with NewsletterCreationScheduleResource once model is available
            return $this->resourceResponse(['schedule' => $schedule->toArray()]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroyCreation(int $newsletterId, int $scheduleId): JsonResponse
    {
        try {
            $this->scheduleService->cancelCreationSchedule($scheduleId);

            return $this->successResponse('Creation schedule cancelled');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Send Schedule
    // =========================================================================

    public function storeSend(CreateNewsletterScheduleRequest $request, int $newsletterId): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->createSendSchedule(
                $newsletterId,
                SiteContext::getId(),
                $request->validated()
            );

            return $this->resourceResponse([
                'schedule' => NewsletterSendScheduleResource::make($schedule)->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateSend(UpdateNewsletterScheduleRequest $request, int $newsletterId, int $scheduleId): JsonResponse
    {
        try {
            $schedule = $this->scheduleService->updateSendSchedule($scheduleId, $request->validated());

            return $this->resourceResponse([
                'schedule' => NewsletterSendScheduleResource::make($schedule)->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroySend(int $newsletterId, int $scheduleId): JsonResponse
    {
        try {
            $this->scheduleService->cancelSendSchedule($scheduleId);

            return $this->successResponse('Send schedule cancelled');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}