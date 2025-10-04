<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Repositories\TagRepository;

class UpdateTagRequest extends FormRequest
{
    private $tagRepository;

    public function __construct()
    {
        parent::__construct();
        $this->tagRepository = new TagRepository();
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

//    public function authorize(): bool
//    {
//        $tag = $this->getTag();
//        return $this->user() && $this->user()->can('update', $tag);
//    }

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
}