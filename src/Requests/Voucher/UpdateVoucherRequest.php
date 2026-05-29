<?php

namespace App\Requests\Voucher;

use App\Enums\Vouchers\VoucherType;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\Vouchers\VoucherRepository;
use App\Requests\Concerns\HandlesSubscriptionVoucherFields;

class UpdateVoucherRequest extends FormRequest
{
    use HandlesSubscriptionVoucherFields;

    private $voucherRepository;

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
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'string|max:1000',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_amount' => 'nullable|integer|min:0',
            'discount_percentage' => 'nullable|integer|min:0|max:100',
            'minimum_order_value' => 'numeric|min:0',
            'maximum_discount' => 'numeric|min:0',
            'usage_limit' => 'integer|min:1',
            'per_user_limit' => 'integer|min:1',
            'applies_to_orders' => 'nullable|boolean',
            'applies_to_subscriptions' => 'nullable|boolean',
            'subscription_discount_duration' => 'nullable|in:once,repeating,forever',
            'subscription_duration_months' => 'nullable|integer|min:1',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'status' => 'in:active,inactive,expired',
            'terms_and_conditions' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                $code = $request->input('code');
                $siteId = $request->input('site_id');
                $voucherId = $this->route('id');

                if ($this->voucherRepository->codeExistsInSite($code, $siteId, $voucherId)) {
                    throw new ValidationException('Voucher code already exists');
                }

                // Validate percentage type
                if ($request->input('type') === VoucherType::Percentage->value && $request->input('value') > 100) {
                    throw new ValidationException('Percentage value cannot exceed 100');
                }

                // Validate date range
                $startsAt = $request->input('starts_at');
                $expiresAt = $request->input('expires_at');

                if ($startsAt && $expiresAt && strtotime($startsAt) >= strtotime($expiresAt)) {
                    throw new ValidationException('Expiry date must be after start date');
                }

                $this->validateSubscriptionVoucherFields($request);
            }
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize code to uppercase
        if (!empty($this->data['code'])) {
            $this->data['code'] = strtoupper(trim($this->data['code']));
        }

        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }

        $this->normalizeSubscriptionVoucherFields();
    }
}
