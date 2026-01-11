<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Models\Brand;
use App\Policies\BrandPolicy;
use App\Repositories\Cms\BrandRepository;

class UpdateBrandRequest extends FormRequest
{
    private BrandRepository $brandRepository;

    public function __construct()
    {
        parent::__construct();
        $this->brandRepository = new BrandRepository();
    }

    protected static function model(): string
    {
        return Brand::class;
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'website' => 'url',
            'is_active' => 'boolean',
            'seo_title' => 'string|max:255',
            'seo_description' => 'string',
            'no_index' => 'boolean',
            'canonical_url' => 'url'
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return BrandPolicy::class;
    }

    protected function getModelForAuthorization()
    {
        $id = $this->route('id');
        return $id ? $this->brandRepository->find($id) : null;
    }

    protected function prepareForValidation(): void
    {
        if (!empty($this->data['name']) && empty($this->data['slug'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}