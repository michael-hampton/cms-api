<?php

declare(strict_types=1);

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

/**
 * Shared rule definitions for create/update.
 *
 * Booleans (is_active, is_default) are deliberately NOT marked 'required'
 * on update, since `false` is a legitimate value and this framework's
 * validated() returns raw input — see BaseReplacementPolicyRequest for the
 * same reasoning. The controller coerces them explicitly via
 * filter_var(..., FILTER_VALIDATE_BOOLEAN) before handing off to the
 * service, matching that existing pattern.
 *
 * `password` is required on create (a connection is useless without one)
 * but only 'sometimes' on update — an empty/omitted password on update
 * means "leave the stored password unchanged" (see
 * PrintVendorConnectionService::update()).
 */
abstract class BasePrintVendorConnectionRequest extends FormRequest
{
    abstract protected function isCreate(): bool;

    public function rules(): array
    {
        $requiredOnCreate = $this->isCreate() ? 'required' : 'sometimes';

        return [
            'name' => "{$requiredOnCreate}|string|max:150",
            'code' => "{$requiredOnCreate}|string|max:100|regex:/^[a-z0-9_-]+$/",
            'connection_type' => "{$requiredOnCreate}|in:label,batch,both",
            'host' => "{$requiredOnCreate}|string|max:255",
            'port' => 'sometimes|integer|min_number:1|max_number:65535',
            'username' => "{$requiredOnCreate}|string|max:255",
            'password' => $this->isCreate() ? 'required|string' : 'sometimes|string',
            'remote_path' => "{$requiredOnCreate}|string|max:500",
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'name is required.',
            'name.max' => 'name must not exceed 150 characters.',
            'code.required' => 'code is required.',
            'code.regex' => 'code may only contain lowercase letters, numbers, hyphens and underscores.',
            'connection_type.in' => 'connection_type must be one of: label, batch, both.',
            'host.required' => 'host is required.',
            'port.min_number' => 'port must be between 1 and 65535.',
            'port.max_number' => 'port must be between 1 and 65535.',
            'username.required' => 'username is required.',
            'password.required' => 'password is required.',
            'remote_path.required' => 'remote_path is required.',
        ];
    }

    /**
     * The known boolean fields, coerced explicitly by the controller
     * after validation — see class docblock.
     */
    public static function booleanFields(): array
    {
        return ['is_active', 'is_default'];
    }
}