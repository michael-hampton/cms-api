<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterCreationScheduleRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
use App\Requests\Newsletter\CreateNewsletterScheduleRequest;
use App\Requests\Newsletter\UpdateNewsletterScheduleRequest;
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

    /**
     * Returns both schedules for the given newsletter.
     * Frontend expects: { schedules: { creation: {...}|null, send: {...}|null } }
     */
    public function index(int $newsletterId): mixed
    {
        $creation = $this->creationRepo->findByNewsletterId($newsletterId);
        $send = $this->sendRepo->findByNewsletterId($newsletterId);

        return $this->resourceResponse([
            'schedules' => [
                'creation' => $creation?->toArray(),
                'send' => $send?->toArray(),
            ],
        ]);
    }

    // =========================================================================
    // Creation Schedule
    // =========================================================================

    /**
     * POST /newsletters/{newsletterId}/schedules/creation
     */
    public function storeCreation(CreateNewsletterScheduleRequest $request, int $newsletterId): mixed
    {
        try {
            $schedule = $this->scheduleService->createCreationSchedule(
                $newsletterId,
                SiteContext::getId(),
                $request->validated()
            );

            return $this->resourceResponse(['schedule' => $schedule->toArray()], 201);

        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /newsletters/{newsletterId}/schedules/creation/{scheduleId}
     */
    public function updateCreation(UpdateNewsletterScheduleRequest $request, int $newsletterId, int $scheduleId): mixed
    {
        try {
            $schedule = $this->scheduleService->updateCreationSchedule($scheduleId, $request->validated());

            return $this->resourceResponse(['schedule' => $schedule->toArray()]);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /newsletters/{newsletterId}/schedules/creation/{scheduleId}
     */
    public function destroyCreation(int $newsletterId, int $scheduleId): mixed
    {
        try {
            $this->scheduleService->cancelCreationSchedule($scheduleId);

            return $this->resourceResponse(['success' => true, 'message' => 'Creation schedule cancelled']);

        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Send Schedule
    // =========================================================================

    /**
     * POST /newsletters/{newsletterId}/schedules/send
     */
    public function storeSend(CreateNewsletterScheduleRequest $request, int $newsletterId): mixed
    {
        try {
            $schedule = $this->scheduleService->createSendSchedule(
                $newsletterId,
                SiteContext::getId(),
                $request->validated()
            );

            return $this->resourceResponse(['schedule' => $schedule->toArray()], 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /newsletters/{newsletterId}/schedules/send/{scheduleId}
     */
    public function updateSend(UpdateNewsletterScheduleRequest $request, int $newsletterId, int $scheduleId): mixed
    {
        try {
            $schedule = $this->scheduleService->updateSendSchedule($scheduleId, $request->validated());

            return $this->resourceResponse(['schedule' => $schedule->toArray()]);

        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /newsletters/{newsletterId}/schedules/send/{scheduleId}
     */
    public function destroySend(int $newsletterId, int $scheduleId): mixed
    {
        try {
            $this->scheduleService->cancelSendSchedule($scheduleId);

            return $this->resourceResponse(['success' => true, 'message' => 'Send schedule cancelled']);

        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}