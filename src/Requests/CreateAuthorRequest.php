<?php

namespace App\Requests;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\Cms\AuthorRepository;

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
            // File uploads skip URL validation; string avatars must still be URLs.
            'avatar' => $this->hasFile('avatar') ? [] : ['url'],
            'website' => ['url'],
            'twitter' => ['string', 'max:255'],
            'linkedin' => ['string', 'max:255'],
            'facebook' => ['string', 'max:255'],
            'status' => ['in:active,inactive'],
            'expertise' => ['nullable', 'string', 'max:800'],
            'location' => ['nullable', 'array'],
            'location.*' => ['string', 'max:255'],
            'education' => ['nullable', 'array'],
            'education.*' => ['string', 'max:255'],
            'awards' => ['nullable', 'array'],
            'awards.*' => ['string', 'max:255'],
            'seniority_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'Author');
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }

        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }

//        if (!isset($this->data['is_active'])) {
//            $this->data['is_active'] = true;
//        }

        // Normalise JSON array fields that may arrive as a string from multipart forms
        foreach (['location', 'education', 'awards'] as $field) {
            if (isset($this->data[$field]) && is_string($this->data[$field])) {
                $this->data[$field] = json_decode($this->data[$field], true) ?? [];
            }
        }
    }

    public function after(): array
    {
        return [
            function ($request) {
                $authorId = $request->route('id') ?? null;

                if ($request->has('email')) {
                    $existing = $this->authorRepository->findByEmail($request->get('email'));
                    if ($existing && (!$authorId || $existing->id !== $authorId)) {
                        throw new ValidationException('Email already exists', ['email' => 'Email already exists']);
                    }
                }

                if ($request->has('slug')) {
                    $existing = $this->authorRepository->findBySlug($request->get('slug'));
                    if ($existing && (!$authorId || $existing->id !== $authorId)) {
                        throw new ValidationException('Slug already exists');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Author name is required',
            'slug.unique' => 'This slug is already in use',
            'email.unique' => 'This email is already registered',
            'email.email' => 'Please provide a valid email address',
            'expertise.max' => 'Expertise may not exceed 800 characters',
            'location.array' => 'Location must be an array of strings',
            'education.array' => 'Education must be an array of strings',
            'awards.array' => 'Awards must be an array of strings',
            'seniority_date.date' => 'Seniority date must be a valid date',
        ];
    }
}