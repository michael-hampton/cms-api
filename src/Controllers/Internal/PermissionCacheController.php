<?php

namespace App\Controllers\Internal;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Services\OpenCollab\PermissionCacheInvalidator;

class PermissionCacheController extends Controller
{
    public function __construct(
        private readonly PermissionCacheInvalidator $invalidator
    ) {
        parent::__construct();
    }

    public function invalidate(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return $this->errorResponse('Unauthorized.', 401);
        }

        $userIds = $this->userIdsFromRequest($request);

        if ($userIds === []) {
            return $this->errorResponse('user_id or user_ids is required.', 422);
        }

        $count = $this->invalidator->invalidateUsers($userIds);

        Logger::info('Internal permission cache invalidation requested', [
            'count' => $count,
            'user_ids' => $userIds,
        ]);

        return $this->resourceResponse([
            'invalidated' => $count,
            'user_ids' => $userIds,
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $expected = $_ENV['INTERNAL_SERVICE_TOKEN']
            ?? getenv('INTERNAL_SERVICE_TOKEN')
            ?: config('app.internal_service_token');

        if (!$expected) {
            return false;
        }

        $provided = $request->header('X-Internal-Token')
            ?? $request->header('X-Internal-Service-Token')
            ?? $this->bearerToken($request);

        return is_string($provided) && hash_equals((string) $expected, $provided);
    }

    private function bearerToken(Request $request): ?string
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return trim(substr($authorization, 7));
    }

    private function userIdsFromRequest(Request $request): array
    {
        $userIds = $request->get('user_ids', []);

        if (!is_array($userIds)) {
            $userIds = [];
        }

        if ($request->get('user_id') !== null) {
            $userIds[] = $request->get('user_id');
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            fn(int $userId) => $userId > 0
        )));
    }
}
