<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\Cms\TagRepository;

class UpdateTagRequest extends FormRequest
{
    private $tagRepository;

    public function __construct()
    {
        parent::__construct();
        $this->tagRepository = new TagRepository();
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update', 'Tag');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string|max:1000',
            'color' => 'string|max:7',
            'is_featured' => 'boolean'
        ];
    }

    public function after(): array
    {
        return [
            function ($request) {
                if (!empty($request->input('slug'))) {
                    $existing = $this->tagRepository->findBySlug($request->input('slug'));
                    if ($existing && $existing->id !== (int)$this->route('id')) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    private function getTag()
    {
        return $this->tagRepository->find((int)$this->route('id'));
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }
    }
}