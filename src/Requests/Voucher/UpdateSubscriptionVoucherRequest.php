<?php

namespace App\Requests\Voucher;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\Vouchers\VoucherRepository;
use App\Requests\Concerns\HandlesSubscriptionVoucherFields;

/**
 * Validates PUT /api/{site}/subscription-vouchers/{id}.
 *
 * Identical business rules to CreateSubscriptionVoucherRequest with one
 * difference: the uniqueness check excludes the voucher being updated so a
 * save without a code change does not reject itself.
 */
class UpdateSubscriptionVoucherRequest extends FormRequest
{
    use HandlesSubscriptionVoucherFields;

    private VoucherRepository $voucherRepository;

    public function __construct()
    {
        parent::__construct();
        $this->voucherRepository = new VoucherRepository();
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', 'Voucher');
    }

    public function rules(): array
    {
        return [
            'code'                           => 'required|string|max:100',
            'name'                           => 'required|string|max:255',
            'description'                    => 'nullable|string',
            'terms_and_conditions'           => 'nullable|string',
            'status'                         => 'in:active,inactive,expired',

            'discount_type'                  => 'required|in:percentage,fixed',
            'discount_amount'                => 'nullable|integer|min:1',
            'discount_percentage'            => 'nullable|integer|min:1|max:100',
            'maximum_discount'               => 'nullable|numeric|min:0',

            'subscription_plan_ids'          => 'nullable|array',
            'subscription_discount_duration' => 'required|in:once,repeating,forever',
            'subscription_duration_months'   => 'nullable|integer|min:1',

            'usage_limit'                    => 'nullable|integer|min:1',
            'per_user_limit'                 => 'nullable|integer|min:1',
            'is_stackable'                   => 'nullable|boolean',

            'starts_at'                      => 'nullable|date',
            'expires_at'                     => 'nullable|date',
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                $this->validateDiscountFields($request);
                $this->validateDurationFields($request);
                $this->validateDateRange($request);
                $this->validateCodeUniqueness($request);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->data['applies_to_subscriptions'] = true;
        $this->data['applies_to_orders']        = false;

        if (!empty($this->data['code'])) {
            $this->data['code'] = strtoupper(trim($this->data['code']));
        }

        $this->normalizeDiscountForLegacyFields();
        $this->normalizeSubscriptionVoucherFields();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateDiscountFields($request): void
    {
        $discountType = $request->input('discount_type');

        if ($discountType === 'percentage' && empty($request->input('discount_percentage'))) {
            throw new ValidationException('discount_percentage is required for percentage discounts');
        }

        if ($discountType === 'fixed' && empty($request->input('discount_amount'))) {
            throw new ValidationException('discount_amount is required for fixed discounts');
        }
    }

    private function validateDurationFields($request): void
    {
        $duration = $request->input('subscription_discount_duration');
        $months   = $request->input('subscription_duration_months');

        if ($duration === 'repeating' && empty($months)) {
            throw new ValidationException('subscription_duration_months is required when duration is repeating');
        }

        if ($duration !== 'repeating' && !empty($months)) {
            throw new ValidationException('subscription_duration_months must not be set when duration is not repeating');
        }

        $this->validateSubscriptionVoucherFields($request);
    }

    private function validateDateRange($request): void
    {
        $startsAt  = $request->input('starts_at');
        $expiresAt = $request->input('expires_at');

        if ($startsAt && $expiresAt && strtotime($expiresAt) <= strtotime($startsAt)) {
            throw new ValidationException('expires_at must be after starts_at');
        }
    }

    private function validateCodeUniqueness($request): void
    {
        $code       = $request->input('code');
        $siteId     = $request->input('site_id');
        // The route param name must be 'id' — matches the controller signature
        // PUT /api/{site}/subscription-vouchers/{id}
        $voucherId  = $this->route('id');

        if ($this->voucherRepository->codeExistsInSite($code, $siteId, $voucherId)) {
            throw new ValidationException('Voucher code already exists for this site');
        }
    }

    private function normalizeDiscountForLegacyFields(): void
    {
        $discountType = $this->data['discount_type'] ?? null;

        if ($discountType === 'percentage' && isset($this->data['discount_percentage'])) {
            $this->data['type']  = 'percentage';
            $this->data['value'] = $this->data['discount_percentage'];
        }

        if ($discountType === 'fixed' && isset($this->data['discount_amount'])) {
            $this->data['type']  = 'fixed';
            $this->data['value'] = $this->data['discount_amount'];
        }
    }
}