<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\AuthorRepository;

class CreateAuthorRequest extends FormRequest
{
    private AuthorRepository $authorRepository;
    public function __construct()
    {
        parent::__construct();

        $this->authorRepository = new AuthorRepository();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['string', 'max:255'],
            'email' => ['email', 'max:255'],
            'bio' => ['string'],
            'avatar' => ['url'],
            'website' => ['url'],
            'twitter' => ['string', 'max:255'],
            'linkedin' => ['string', 'max:255'],
            'facebook' => ['string', 'max:255'],
            'status' => ['in:active,inactive']
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

//        if (!isset($this->data['status'])) {
//            $this->data['status'] = 'active';
//        }
    }

    public function after(): array
    {
        return [
            function ($request) {
            $authorId = $request->route('id') ?? null;
                // Check for unique email
                if ($request->has('email')) {
                    $existing = $this->authorRepository->findByEmail($request->get('email'));;
                    if ($existing && (!$authorId || $existing->id !== $authorId)) {
                        throw new ValidationException('Email already exists');
                    }
                }

                // Check for unique slug
                if ($request->has('slug')) {
                    $existing = $this->authorRepository->findBySlug($request->get('slug'));
                    if ($existing && (!$authorId || $existing->id !== $authorId)) {
                       throw new ValidationException('Slug already exists');
                    }
                }
            }
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Author name is required',
            'slug.unique' => 'This slug is already in use',
            'email.unique' => 'This email is already registered',
            'email.email' => 'Please provide a valid email address'
        ];
    }
}