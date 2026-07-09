<?php

declare(strict_types=1);

namespace App\Requests\Subscription;

use App\Framework\Http\FormRequest;

/**
 * Shared rule definitions for create/update. Booleans are deliberately
 * NOT marked 'required' — this framework's convertPipeRulesToValidationRules()
 * maps 'required' to RequiredRule, and I don't have RequiredRule's
 * implementation to confirm it treats boolean `false` as present rather
 * than "empty" (a very common validation-library gotcha: `empty(false)`
 * is `true` in PHP). Given `allows_replacements: false` /
 * `require_stock: false` are completely valid, common values here (the
 * "No Replacement" policy is false across the board), I'd rather not
 * risk rejecting a legitimate false as "missing". `name` is the one
 * field where "required" and "must be a non-empty string" are the same
 * check, so that risk doesn't apply.
 *
 * Booleans are still coerced explicitly in the controller via
 * filter_var(..., FILTER_VALIDATE_BOOLEAN) before being handed to the
 * service, matching the pattern already used in
 * CrmIssueResolutionController for `business_decision` — this
 * framework's validated() returns raw input values, not cast ones, so
 * that coercion is the caller's responsibility either way.
 */
abstract class BaseReplacementPolicyRequest extends FormRequest
{
    abstract protected function isCreate(): bool;

    public function rules(): array
    {
        $nameRule = $this->isCreate() ? 'required|string|max:150' : 'sometimes|string|max:150';

        return [
            'name' => $nameRule,
            'description' => 'sometimes|string',
            'allows_replacements' => 'sometimes|boolean',
            'allows_extensions' => 'sometimes|boolean',
            'max_replacements' => 'sometimes|nullable|integer|min_number:0',
            'max_extensions' => 'sometimes|nullable|integer|min_number:0',
            'replacement_limit_scope' => 'sometimes|in:per_issue,per_subscription,per_year,lifetime',
            'extension_limit_scope' => 'sometimes|in:per_issue,per_subscription,per_year,lifetime',
            'require_stock' => 'sometimes|boolean',
            'requires_manager_approval' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'name is required.',
            'name.max' => 'name must not exceed 150 characters.',
            'replacement_limit_scope.in' => 'replacement_limit_scope must be one of: per_issue, per_subscription, per_year, lifetime.',
            'extension_limit_scope.in' => 'extension_limit_scope must be one of: per_issue, per_subscription, per_year, lifetime.',
            'max_replacements.min_number' => 'max_replacements must be zero or greater, or null for unlimited.',
            'max_extensions.min_number' => 'max_extensions must be zero or greater, or null for unlimited.',
        ];
    }

    /**
     * The known boolean fields, coerced explicitly by the controller
     * after validation — see class docblock.
     */
    public static function booleanFields(): array
    {
        return [
            'allows_replacements',
            'allows_extensions',
            'require_stock',
            'requires_manager_approval',
            'is_default',
            'active',
        ];
    }
}