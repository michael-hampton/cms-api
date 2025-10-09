<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Models\Site;
use App\Repositories\AuthorRepository;
use App\Repositories\UserRepository;

class UpdateUserRequest extends FormRequest
{
    private UserRepository $userRepository;
    public function __construct()
    {
        parent::__construct();

        $this->userRepository = new UserRepository();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                //Rule::unique('users')->ignore($this->route('user'))
            ],
            'password' => ['sometimes', 'confirmed'],
            'role' => ['sometimes', 'in:admin,user'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Only check email uniqueness if email is being updated
                if (!$request->has('email')) {
                    return;
                }

                $userId = $request->route('id') ?? null;
                $site = $request->route('site') ?? null;
                $siteId = !empty($site) ? Site::resolveSite($site->id) : 1;

                // Find user with this email in the same site
                $existing = $this->userRepository->findByEmail($request->get('email'), $siteId);

                // If email exists and it's not the current user being updated, throw error
                if ($existing && (int)$existing->id !== (int)$userId) {
                    throw new ValidationException('The email has already been taken.');
                }
            }
        ];
    }
}