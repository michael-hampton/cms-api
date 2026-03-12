<?php

namespace App\Framework\Validation;

use App\Framework\Exceptions\ValidationException;

/**
 * Adds a reusable "name must be unique" after-hook to any FormRequest.
 *
 * Usage in a FormRequest:
 *
 *   use UniqueNameValidation;
 *
 *   public function after(): array
 *   {
 *       return [
 *           ...$this->uniqueNameAfterHooks(
 *               repository: $this->productRepository,
 *               routeIdParam: 'id',          // optional, defaults to 'id'
 *               field: 'name',               // optional, defaults to 'name'
 *               errorMessage: 'A product with this name already exists.',
 *           ),
 *       ];
 *   }
 *
 * The repository must implement findByName(string $name): ?object
 */
trait UniqueNameValidation
{
    /**
     * Returns a closure suitable for use in FormRequest::after() that asserts
     * the given field value is unique within the repository.
     *
     * On an update request the current record (identified by $routeIdParam) is
     * excluded so a record can be saved with its own existing name.
     *
     * @param object $repository Repository with a findByName(string): ?object method.
     * @param string $routeIdParam The route parameter name that holds the current record ID (for updates).
     * @param string $field The request field to check.
     * @param string $errorMessage The validation error message.
     * @return array<\Closure>
     */
    protected function uniqueNameAfterHooks(
        object $repository,
        string $routeIdParam = 'id',
        string $field = 'name',
        string $errorMessage = 'This name is already in use.',
    ): array
    {
        return [
            function ($request) use ($repository, $routeIdParam, $field, $errorMessage) {
                if (!$request->has($field)) {
                    return;
                }

                $value = $request->get($field);
                $currentId = $request->route($routeIdParam);
                $existing = $repository->findByName($value);

                if ($existing && (!$currentId || (int)$existing->id !== (int)$currentId)) {
                    throw new ValidationException(
                        $errorMessage,
                        [$field => $errorMessage]
                    );
                }
            },
        ];
    }
}