<?php

namespace App\Requests;

use App\Framework\Database\Database;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\Cms\SiteRepository;

class CreateSiteRequest extends FormRequest
{
    private SiteRepository $siteRepository;

    public function __construct()
    {
        parent::__construct();
        $this->siteRepository = new SiteRepository(Database::getInstance());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'domain' => ['string', 'max:255'],
            'subdomain' => ['string', 'max:255'],
            'theme' => ['string', 'max:100'],
            'logo' => ['string', 'max:500'],
            'favicon' => ['string', 'max:500'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean']
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (!isset($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }

        if (!isset($this->data['is_default'])) {
            $this->data['is_default'] = false;
        }
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Check for unique domain
                if ($request->has('domain') && !empty($request->get('domain'))) {
                    if ($this->siteRepository->existsByDomain($request->get('domain'))) {
                        throw new ValidationException('Domain already exists');
                    }
                }

                // Check for unique slug
                if ($request->has('slug')) {
                    if ($this->siteRepository->existsBySlug($request->get('slug'))) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Site name is required',
            'slug.required' => 'Site slug is required',
            'slug.max' => 'Slug must not exceed 255 characters',
            'domain.max' => 'Domain must not exceed 255 characters',
            'theme.max' => 'Theme must not exceed 100 characters'
        ];
    }
}