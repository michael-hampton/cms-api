<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Models\Territory;
use App\Policies\TerritoryPolicy;
use App\Repositories\Cms\RegionSetRepository;
use App\Repositories\Cms\TerritoryRepository;

class UpdateTerritoryRequest extends FormRequest
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
            'name' => 'string|max:255',
            'code' => 'string|max:50',
            'slug' => 'string|max:255',
            'region_set_id' => 'integer|exists:region_sets,id',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                $id = $request->route('id');

                // Check if region set exists
                if ($request->has('region_set_id')) {
                    $regionSetId = $request->get('region_set_id');
                    $regionSet = $this->regionSetRepository->find($regionSetId);
                    if (!$regionSet) {
                        throw new ValidationException('Region set not found');
                    }
                }

                // Check slug uniqueness
                if ($request->has('slug')) {
                    $existing = $this->territoryRepository->findBySlug($request->get('slug'));
                    if ($existing && $existing->id !== (int)$id) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return TerritoryPolicy::class;
    }

    protected function prepareForValidation(): void
    {
        if (!empty($this->data['name']) && empty($this->data['slug'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}