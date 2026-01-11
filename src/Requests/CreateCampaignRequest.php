<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\SiteContext;
use App\Framework\Support\Str;
use App\Models\Campaign;
use App\Policies\CampaignPolicy;
use App\Repositories\Cms\CampaignRepository;

class CreateCampaignRequest extends FormRequest
{
    private CampaignRepository $campaignRepository;

    public function __construct()
    {
        parent::__construct();
        $this->campaignRepository = new CampaignRepository();
    }

    protected static function model(): string
    {
        return Campaign::class;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'string',
            'newsletter_id' => 'integer',
            'is_active' => 'boolean',
            'gates_premium_content' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'tracking_params' => 'array'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                $siteId = SiteContext::getId();
                $slug = $request->get('slug');

                $existing = $this->campaignRepository->findBySlug($slug, $siteId);
                if ($existing) {
                    throw new ValidationException('Slug already exists');
                }
            }
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return CampaignPolicy::class;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (!isset($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }

        if (!isset($this->data['gates_premium_content'])) {
            $this->data['gates_premium_content'] = false;
        }
    }
}