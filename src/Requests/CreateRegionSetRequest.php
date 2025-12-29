<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Models\RegionSet;
use App\Policies\RegionSetPolicy;
use App\Repositories\RegionSetRepository;

class CreateRegionSetRequest extends FormRequest
{
    private RegionSetRepository $regionSetRepository;

    public function __construct()
    {
        parent::__construct();
        $this->regionSetRepository = new RegionSetRepository();
    }

    protected static function model(): string
    {
        return RegionSet::class;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'territories' => 'array',
            'territories.*.name' => 'required|string|max:255',
            'territories.*.code' => 'string|max:50',
            'territories.*.slug' => 'string|max:255',
            'territories.*.is_active' => 'boolean',
            'territories.*.sort_order' => 'integer'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                if ($request->has('slug')) {
                    $existing = $this->regionSetRepository->findBySlug($request->get('slug'));
                    if ($existing) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Region set name is required',
            'territories.*.name.required' => 'Territory name is required'
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return RegionSetPolicy::class;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (!isset($this->data['is_active'])) {
            $this->data['is_active'] = true;
        }

        if (!isset($this->data['sort_order'])) {
            $this->data['sort_order'] = 0;
        }
    }
}