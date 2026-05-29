<?php

namespace App\Requests\Voucher;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\SiteContext;
use App\Repositories\Vouchers\VoucherRepository;
use App\Requests\Concerns\HandlesSubscriptionVoucherFields;

/**
 * Validates POST /api/{site}/subscription-vouchers.
 *
 * Key differences from CreateVoucherRequest:
 *   - applies_to_subscriptions is forced to true in prepareForValidation, not
 *     accepted from the caller — the endpoint cannot create order vouchers
 *   - discount_type / discount_amount / discount_percentage use the frontend's
 *     field names rather than the legacy type/value convention
 *   - subscription_discount_duration + subscription_duration_months carry
 *     conditional validation (duration_months required iff duration = repeating,
 *     and forbidden otherwise)
 */
class CreateSubscriptionVoucherRequest extends FormRequest
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
        return $this->user() && $this->user()->can('create', 'Voucher');
    }

    public function rules(): array
    {
        return [
            // Basic details
            'code'                           => 'required|string|max:100',
            'name'                           => 'required|string|max:255',
            'description'                    => 'nullable|string',
            'terms_and_conditions'           => 'nullable|string',
            'status'                         => 'in:active,inactive,expired',

            // Discount — both legacy (type/value) and explicit (discount_type/discount_amount/
            // discount_percentage) fields are accepted so the Angular payload round-trips cleanly
            'discount_type'                  => 'required|in:percentage,fixed',
            'discount_amount'                => 'nullable|integer|min:1',
            'discount_percentage'            => 'nullable|integer|min:1|max:100',
            'maximum_discount'               => 'nullable|numeric|min:0',

            // Subscription settings
            'subscription_plan_ids'          => 'nullable|array',
            'subscription_discount_duration' => 'required|in:once,repeating,forever',
            'subscription_duration_months'   => 'nullable|integer|min:1',

            // Usage rules
            'usage_limit'                    => 'nullable|integer|min:1',
            'per_user_limit'                 => 'nullable|integer|min:1',
            'is_stackable'                   => 'nullable|boolean',

            // Dates
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
        // Force subscription scope — the endpoint only creates subscription vouchers
        $this->data['applies_to_subscriptions'] = true;
        $this->data['applies_to_orders']        = false;

        if (!empty($this->data['code'])) {
            $this->data['code'] = strtoupper(trim($this->data['code']));
        }

        if (!isset($this->data['status'])) {
            $this->data['status'] = 'active';
        }

        if (!isset($this->data['usage_count'])) {
            $this->data['usage_count'] = 0;
        }

        // Mirror discount_type into the legacy type field and derive value from
        // the explicit amount/percentage field so VoucherService::create() works
        // without changes.
        $this->normalizeDiscountForLegacyFields();

        $this->normalizeSubscriptionVoucherFields();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateDiscountFields($request): void
    {
        $discountType = $request->input('discount_type');

        if ($discountType === 'percentage') {
            if (empty($request->input('discount_percentage'))) {
                throw new ValidationException('discount_percentage is required for percentage discounts');
            }
        }

        if ($discountType === 'fixed') {
            if (empty($request->input('discount_amount'))) {
                throw new ValidationException('discount_amount is required for fixed discounts');
            }
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
        $code   = $request->input('code');
        $siteId = SiteContext::getId();

        if ($this->voucherRepository->codeExistsInSite($code, $siteId)) {
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
            // discount_amount is stored in pence; value keeps the same unit for
            // consistency with how VoucherService reads it.
            $this->data['value'] = $this->data['discount_amount'];
        }
    }
}