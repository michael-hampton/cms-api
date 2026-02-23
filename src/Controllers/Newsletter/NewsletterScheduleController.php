<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterCreationScheduleRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
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

    /**
     * GET /newsletters/{newsletterId}/schedules
     *
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
    public function storeCreation(Request $request, int $newsletterId): mixed
    {
        $data = $request->all();

        $validation = $this->validateSchedulePayload($data);
        if ($validation !== null) {
            return $validation;
        }

        try {
            $schedule = $this->scheduleService->createCreationSchedule(
                $newsletterId,
                SiteContext::getId(),
                $data
            );

            return $this->resourceResponse(['schedule' => $schedule->toArray()], 201);

        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function validateSchedulePayload(array $data): mixed
    {
        $errors = [];

        if (empty($data['frequency'])) {
            $errors['frequency'] = 'frequency is required';
        } elseif (!in_array($data['frequency'], ['daily', 'weekly', 'monthly'], true)) {
            $errors['frequency'] = 'frequency must be daily, weekly, or monthly';
        }

        if (($data['frequency'] ?? null) === 'weekly' && !isset($data['day_of_week'])) {
            $errors['day_of_week'] = 'day_of_week is required for weekly schedules';
        }

        if (($data['frequency'] ?? null) === 'monthly' && empty($data['day_of_month'])) {
            $errors['day_of_month'] = 'day_of_month is required for monthly schedules';
        }

        if (empty($data['time'])) {
            $errors['time'] = 'time is required';
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $data['time'])) {
            $errors['time'] = 'time must be in HH:MM format';
        }

        if (!empty($errors)) {
            return $this->resourceResponse(['success' => false, 'errors' => $errors], 422);
        }

        return null;
    }

    /**
     * PUT /newsletters/{newsletterId}/schedules/creation/{scheduleId}
     */
    public function updateCreation(Request $request, int $newsletterId, int $scheduleId): mixed
    {
        $data = $request->all();

        try {
            $schedule = $this->scheduleService->updateCreationSchedule($scheduleId, $data);

            return $this->resourceResponse(['schedule' => $schedule->toArray()]);

        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Send Schedule
    // =========================================================================

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

    /**
     * POST /newsletters/{newsletterId}/schedules/send
     */
    public function storeSend(Request $request, int $newsletterId): mixed
    {
        $data = $request->all();

        $validation = $this->validateSchedulePayload($data);
        if ($validation !== null) {
            return $validation;
        }

        try {
            $schedule = $this->scheduleService->createSendSchedule(
                $newsletterId,
                SiteContext::getId(),
                $data
            );

            return $this->resourceResponse(['schedule' => $schedule->toArray()], 201);

        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /newsletters/{newsletterId}/schedules/send/{scheduleId}
     */
    public function updateSend(Request $request, int $newsletterId, int $scheduleId): mixed
    {
        $data = $request->all();

        try {
            $schedule = $this->scheduleService->updateSendSchedule($scheduleId, $data);

            return $this->resourceResponse(['schedule' => $schedule->toArray()]);

        } catch (DomainException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Internals
    // =========================================================================

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