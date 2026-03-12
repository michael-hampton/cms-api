<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Framework\Validation\UniqueNameValidation;
use App\Repositories\Product\ProductRepository;

class CreateProductRequest extends FormRequest
{
    use UniqueNameValidation;

    private ProductRepository $productRepository;

    public function __construct()
    {
        parent::__construct();

        $this->productRepository = app(ProductRepository::class);
    }

    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', 'Product');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['string'],
            'price' => ['required', 'numeric', 'min_number:0'],
            'sale_price' => ['numeric', 'min_number:0'],
            'category_id' => ['string', 'max:255'],
            'brand' => ['string', 'max:255'],
            'image' => ['string'],
            'meta_title' => ['string', 'max:255'],
            'meta_description' => ['string', 'max:500'],
            'meta_keywords' => ['string', 'max:500'],

            // Images
            'images' => ['nullable', 'array'],
            'images.*.url' => ['required', 'string'],
            'images.*.alt' => ['nullable', 'string'],
            'images.*.is_primary' => ['boolean'],
            'images.*.sort_order' => ['integer'],

            // Merchants
            'merchants' => ['nullable', 'array', 'min:1'],
            'merchants.*.name' => ['required', 'string'],
            'merchants.*.url' => ['required', 'url'],
            'merchants.*.price' => ['required', 'numeric', 'min_number:0'],
            'merchants.*.is_available' => ['boolean'],

            // Variants
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['required', 'string'],
            'variants.*.attributes' => ['array'],
            'variants.*.price_modifier' => ['numeric'],
            'variants.*.is_active' => ['boolean'],

            // Specifications
            'specifications' => ['nullable', 'array'],
            'specifications.*.category' => ['nullable', 'string'],
            'specifications.*.key' => ['required', 'string'],
            'specifications.*.value' => ['required', 'string'],
            'specifications.*.sort_order' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [
            ...$this->uniqueNameAfterHooks(
                repository: $this->productRepository,
                field: 'name',
                errorMessage: 'A product with this name already exists.',
            ),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'price.required' => 'Price is required',
            'price.min' => 'Price must be greater than or equal to 0',
            'sale_price.min' => 'Sale price must be greater than or equal to 0',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->data['site_id'])) {
            $this->data['site_id'] = config('app.default_site_id');
        }

        if (empty($this->data['slug']) && !empty($this->data['name'])) {
            $this->data['slug'] = Str::slug($this->data['name']);
        }
    }
}