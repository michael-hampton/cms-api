<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Models\Territory;
use App\Policies\TerritoryPolicy;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Cms\TerritoryRepository;

class CreateTerritoryRequest extends FormRequest
{
    private TerritoryRepository $territoryRepository;
    private RegionSetRepository $regionSetRepository;

    public function __construct()
    {
        parent::__construct();
        $this->territoryRepository = new TerritoryRepository();
        $this->regionSetRepository = new RegionSetRepository();
    }

    protected static function model(): string
    {
        return Territory::class;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'string|max:50',
            'slug' => 'string|max:255',
            'region_set_id' => 'required|integer|exists:region_sets,id',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                // Check if region set exists
                $regionSetId = $request->get('region_set_id');
                if ($regionSetId) {
                    $regionSet = $this->regionSetRepository->find($regionSetId);
                    if (!$regionSet) {
                        throw new ValidationException('Region set not found');
                    }
                }

                // Check slug uniqueness
                if ($request->has('slug')) {
                    $existing = $this->territoryRepository->findBySlug($request->get('slug'));
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
            'name.required' => 'Territory name is required',
            'region_set_id.required' => 'Region set is required',
            'region_set_id.exists' => 'Selected region set does not exist'
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return TerritoryPolicy::class;
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