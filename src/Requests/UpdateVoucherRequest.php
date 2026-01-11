<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\Cms\VoucherRepository;

class UpdateVoucherRequest extends FormRequest
{
    private $voucherRepository;

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
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'string|max:1000',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'minimum_order_value' => 'numeric|min:0',
            'maximum_discount' => 'numeric|min:0',
            'usage_limit' => 'integer|min:1',
            'per_user_limit' => 'integer|min:1',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'status' => 'in:active,inactive,expired'
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
                if ($request->input('type') === 'percentage' && $request->input('value') > 100) {
                    throw new ValidationException('Percentage value cannot exceed 100');
                }

                // Validate date range
                $startsAt = $request->input('starts_at');
                $expiresAt = $request->input('expires_at');

                if ($startsAt && $expiresAt && strtotime($startsAt) >= strtotime($expiresAt)) {
                    throw new ValidationException('Expiry date must be after start date');
                }
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
    }
}