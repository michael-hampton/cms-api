<?php

namespace App\Requests;

use App\Framework\Database\Database;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\SiteRepository;

class UpdateSiteRequest extends FormRequest
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
            'name' => ['string', 'max:255'],
            'slug' => ['string', 'max:255'],
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
        // Auto-generate slug if name is provided but slug is not
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }

    public function after(): array
    {
        return [
            function ($request) {
                $siteId = $request->route('id') ?? null;

                // Check for unique domain
                if ($request->has('domain') && !empty($request->get('domain'))) {
                    if ($this->siteRepository->existsByDomain($request->get('domain'), $siteId)) {
                        throw new ValidationException('Domain already exists');
                    }
                }

                // Check for unique slug
                if ($request->has('slug')) {
                    if ($this->siteRepository->existsBySlug($request->get('slug'), $siteId)) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Name must not exceed 255 characters',
            'slug.max' => 'Slug must not exceed 255 characters',
            'domain.max' => 'Domain must not exceed 255 characters',
            'theme.max' => 'Theme must not exceed 100 characters'
        ];
    }
}