<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Requests\Subscription\SubscriptionCommunicationRequest;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationService;

class SubscriptionCommunicationController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationService $service,
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->resourceResponse([
            'communications' => $this->service->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($errors = $this->communicationErrors($request)) {
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        $data = $this->communicationData($request);

        try {
            $communication = $this->service->create($data);

            return $this->jsonResponse(['communication' => $communication->toArray()], 201);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage());
        }

    }

    private function communicationErrors(Request $request): array
    {
        $data = $request->all();
        $errors = [];
        $allowedTypes = array_column(CommunicationTypeEnum::cases(), 'value');
        $allowedChannels = ['email', 'in_app'];

        foreach (['key', 'name', 'type', 'template'] as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                $errors[$field] = "The {$field} field is required.";
            }
        }

        if (isset($data['type']) && !in_array($data['type'], $allowedTypes, true)) {
            $errors['type'] = 'The type field is invalid.';
        }

        if (!isset($data['channels']) || !is_array($data['channels']) || count($data['channels']) === 0) {
            $errors['channels'] = 'The channels field must be a non-empty array.';
        } else {
            foreach ($data['channels'] as $channel) {
                if (!in_array($channel, $allowedChannels, true)) {
                    $errors['channels'] = 'The channels field contains an invalid channel.';
                    break;
                }
            }
        }

        return $errors;
    }

    private function communicationData(Request $request): array
    {
        $data = $request->all();
        $allowedTypes = array_column(CommunicationTypeEnum::cases(), 'value');
        $channels = $data['channels'] ?? [];

        if (!is_array($channels)) {
            $channels = [$channels];
        }

        return [
            'key' => (string)($data['key'] ?? ''),
            'name' => (string)($data['name'] ?? ''),
            'description' => $data['description'] ?? null,
            'type' => in_array($data['type'] ?? null, $allowedTypes, true)
                ? $data['type']
                : CommunicationTypeEnum::ACKNOWLEDGEMENT->value,
            'template' => (string)($data['template'] ?? ''),
            'channels' => array_values($channels),
            'segment_id' => isset($data['segment_id']) ? (int)$data['segment_id'] : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true,
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
        ];
    }

    public function show(int $subscription_communication): JsonResponse
    {
        $communication = $this->service->findWithSchedules($subscription_communication);

        if ($communication === null) {
            return $this->errorResponse('Not found.', 404);
        }

        return $this->jsonResponse(['communication' => $communication->toArray()]);
    }

    public function destroy(int $subscription_communication): JsonResponse
    {
        if (!$this->service->delete($subscription_communication)) {
            return $this->errorResponse('Not found.', 404);
        }

        return $this->jsonResponse(['message' => 'Subscription communication deleted.']);
    }

    public function schedules(int $id): JsonResponse
    {
        try {
            return $this->resourceResponse([
                'schedules' => $this->service->schedulesForCommunication($id),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function storeSchedule(int $id, SubscriptionCommunicationRequest $request): JsonResponse
    {
        try {
            $schedule = $this->service->createSchedule($id, $request->validated());
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }

        return $this->resourceResponse(['schedule' => $schedule->toArray()], 201);
    }

    public function updateSchedule(int $id, SubscriptionCommunicationRequest $request): JsonResponse
    {
        $schedule = $this->service->updateSchedule($id, $request->validated());

        if (!$schedule) {
            return $this->errorResponse('Subscription communication schedule not found.', 404);
        }

        return $this->resourceResponse(['schedule' => $schedule->toArray()]);
    }

    public function update(int $subscription_communication, Request $request): JsonResponse
    {
        if ($errors = $this->communicationErrors($request)) {
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        $data = $this->communicationData($request);

        $communication = $this->service->update($subscription_communication, $data);

        if (!$communication) {
            return $this->errorResponse('Not found.', 404);
        }

        return $this->jsonResponse(['communication' => $communication->toArray()]);
    }

    public function destroySchedule(int $id): JsonResponse
    {
        if (!$this->service->deleteSchedule($id)) {
            return $this->errorResponse('Subscription communication schedule not found.', 404);
        }

        return $this->jsonResponse(['message' => 'Subscription communication schedule deleted.']);
    }
}
