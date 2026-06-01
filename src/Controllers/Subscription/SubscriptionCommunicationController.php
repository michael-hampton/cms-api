<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Requests\Subscription\SubscriptionCommunicationRequest;

class SubscriptionCommunicationController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationRepository $repository,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->jsonResponse([
            'communications' => SubscriptionCommunication::with('schedules')->orderBy('sort_order')->get()->toArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($errors = $this->communicationErrors($request)) {
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        $data = $this->communicationData($request);

        $communication = SubscriptionCommunication::create($data);

        return $this->jsonResponse(['communication' => $communication->toArray()], 201);
    }

    public function show(int $subscription_communication): JsonResponse
    {
        $communication = $this->repository->findWithSchedules($subscription_communication);

        if ($communication === null) {
            return $this->errorResponse('Not found.', 404);
        }

        return $this->jsonResponse(['communication' => $communication->toArray()]);
    }

    public function update(int $subscription_communication, Request $request): JsonResponse
    {
        $communication = SubscriptionCommunication::findOrFail($subscription_communication);

        if ($errors = $this->communicationErrors($request)) {
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        $data = $this->communicationData($request);

        $communication->update($data);

        return $this->jsonResponse(['communication' => $communication->toArray()]);
    }

    public function destroy(int $subscription_communication): JsonResponse
    {
        SubscriptionCommunication::findOrFail($subscription_communication)->delete();

        return $this->jsonResponse(['message' => 'Subscription communication deleted.']);
    }

    public function storeSchedule(int $id, SubscriptionCommunicationRequest $request): JsonResponse
    {
        $communication = SubscriptionCommunication::findOrFail($id);

        $data = $request->validated();

        $schedule = SubscriptionCommunicationSchedule::create(array_merge($data, [
            'subscription_communication_id' => $communication->id,
        ]));

        return $this->jsonResponse(['schedule' => $schedule->toArray()], 201);
    }

    public function updateSchedule(int $id, SubscriptionCommunicationRequest $request): JsonResponse
    {
        $schedule = SubscriptionCommunicationSchedule::findOrFail($id);

        $data = $request->validated();

        $schedule->update($data);

        return $this->jsonResponse(['schedule' => $schedule->toArray()]);
    }

    public function destroySchedule(int $id): JsonResponse
    {
        SubscriptionCommunicationSchedule::findOrFail($id)->delete();

        return $this->jsonResponse(['message' => 'Subscription communication schedule deleted.']);
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
            'key'         => (string) ($data['key'] ?? ''),
            'name'        => (string) ($data['name'] ?? ''),
            'description' => $data['description'] ?? null,
            'type'        => in_array($data['type'] ?? null, $allowedTypes, true)
                ? $data['type']
                : CommunicationTypeEnum::ACKNOWLEDGEMENT->value,
            'template'    => (string) ($data['template'] ?? ''),
            'channels'    => array_values($channels),
            'segment_id'  => isset($data['segment_id']) ? (int) $data['segment_id'] : null,
            'is_active'   => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order'  => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        ];
    }

    private function communicationErrors(Request $request): array
    {
        $data = $request->all();
        $errors = [];
        $allowedTypes = array_column(CommunicationTypeEnum::cases(), 'value');
        $allowedChannels = ['email', 'in_app'];

        foreach (['key', 'name', 'type', 'template'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
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
}
