<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Models\RegionSet;
use App\Policies\RegionSetPolicy;
use App\Repositories\RegionSetRepository;

class UpdateRegionSetRequest extends FormRequest
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
            'name' => 'string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'territories' => 'array',
            'territories.*.id' => 'integer',
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
                $id = $request->route('id');

                if ($request->has('slug')) {
                    $existing = $this->regionSetRepository->findBySlug($request->get('slug'));
                    if ($existing && $existing->id !== (int)$id) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return RegionSetPolicy::class;
    }

    protected function prepareForValidation(): void
    {
        if (!empty($this->data['name']) && empty($this->data['slug'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}