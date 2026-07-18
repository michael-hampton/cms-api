<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\SubscriptionCommunicationLetterCode;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationLetterCodeService;

/**
 * Admin CRUD for the letter code registry (subscription_communication_letter_codes).
 * One row per communication — see the table's unique constraint.
 */
class SubscriptionCommunicationLetterCodeController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationLetterCodeService $service,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        $codes = $this->service->all();

        return $this->resourceResponse([
            'letter_codes' => array_map(
                fn (SubscriptionCommunicationLetterCode $code) => $this->present($code),
                $codes->all(),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        $errors = $this->validate($data);

        if ($errors) {
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        try {
            $code = $this->service->create(
                communicationId: (int) $data['subscription_communication_id'],
                letterCode: $data['letter_code'],
                description: $data['description'] ?? null,
            );

            return $this->resourceResponse(['letter_code' => $this->present($code)], 201);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 409);
        }
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $data = $request->all();
        $errors = $this->validate($data, requireCommunication: false);

        if ($errors) {
            return $this->errorResponse('Validation failed', 422, $errors);
        }

        try {
            $code = $this->service->update(
                id: $id,
                letterCode: $data['letter_code'],
                description: $data['description'] ?? null,
            );

            return $this->resourceResponse(['letter_code' => $this->present($code)]);
        } catch (\RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'not found') ? 404 : 409;
            return $this->errorResponse($exception->getMessage(), $status);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->service->delete($id)) {
            return $this->errorResponse('Not found.', 404);
        }

        return $this->jsonResponse(['message' => 'Letter code deleted.']);
    }

    private function present(SubscriptionCommunicationLetterCode $code): array
    {
        $communication = $code->communication();

        return [
            'id' => $code->id,
            'subscription_communication_id' => $code->subscription_communication_id,
            'communication_key' => $communication?->key,
            'communication_name' => $communication?->name,
            'letter_code' => $code->letter_code,
            'description' => $code->description,
            'created_at' => $code->created_at,
            'updated_at' => $code->updated_at,
        ];
    }

    private function validate(array $data, bool $requireCommunication = true): array
    {
        $errors = [];

        if ($requireCommunication && empty($data['subscription_communication_id'])) {
            $errors['subscription_communication_id'] = 'The subscription_communication_id field is required.';
        }

        if (empty($data['letter_code'])) {
            $errors['letter_code'] = 'The letter_code field is required.';
        } elseif (!preg_match('/^[A-Z0-9_-]{2,20}$/', $data['letter_code'])) {
            $errors['letter_code'] = 'letter_code must be 2-20 uppercase letters, numbers, hyphens or underscores.';
        }

        return $errors;
    }
}
