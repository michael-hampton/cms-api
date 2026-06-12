<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\Cms\UserRepository;

class UpdateUserRequest extends FormRequest
{
    private UserRepository $userRepository;

    public function __construct(array $data = [], array $files = [], array $routeParams = [])
    {
        parent::__construct($data, $files, $routeParams);

        $this->userRepository = new UserRepository();
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', 'User');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'password' => ['sometimes', 'confirmed'],
            'role' => ['sometimes', 'in:admin,user'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (UpdateUserRequest $request): void {
                if (!$request->has('email')) {
                    return;
                }

                $userId = $this->routeUserId($request);

                if ($userId === null) {
                    return;
                }

                $existing = $this->userRepository->findByEmail(
                    (string) $request->get('email')
                );

                if (!$existing) {
                    return;
                }

                if ((int) $existing->id === $userId) {
                    return;
                }

                throw new ValidationException('Validation failed', [
                    'email' => ['The email has already been taken.'],
                ]);
            },
        ];
    }

    private function routeUserId(UpdateUserRequest $request): ?int
    {
        $id = $request->route('id');

        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }
}