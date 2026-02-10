<?php

namespace App\Parsers\Dtos;

final class ProductBlockDto extends BaseBlockDto
{
    private const ALLOWED_DISPLAY_AS = ['button', 'link', 'card'];
    private const ALLOWED_LAYOUTS = ['standard', 'compact', 'detailed'];

    private const KNOWN_KEYS = [
        'link',
        'image', 'name', 'price',
        'description',
    ];

    public function __construct(
        public string $link,
        public bool   $noFollow,
        public bool   $sponsored,
        public bool   $openInNewTab,
        public string $displayAs,
        public string $linkText,
        public ?array $image,
        public string $name,
        public string $brand,
        public string $productName,
        public string $currency,
        public float  $price,
        public float  $salePrice,
        public string $layout,
        public string $description,
        public bool   $showReviewPanel,
        public ?array $review,
        public ?int   $productId,
        public ?int   $variantId,
        public bool   $optedOutProductMatch
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'link' => '',
            'noFollow' => false,
            'sponsored' => false,
            'openInNewTab' => false,
            'displayAs' => 'button',
            'linkText' => 'Buy Now',
            'image' => null,
            'name' => '',
            'brand' => '',
            'productName' => '',
            'currency' => '$',
            'price' => 0.0,
            'salePrice' => 0.0,
            'layout' => 'standard',
            'description' => '',
            'showReviewPanel' => false,
            'review' => null,
            'product_id' => null,
            'variant_id' => null,
            'opted_out_product_match' => false
        ]);

        return new self(
            $data['link'],
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            self::validateEnum($data['displayAs'], self::ALLOWED_DISPLAY_AS, 'button', 'displayAs'),
            $data['linkText'],
            $data['image'],
            trim($data['name']),
            trim($data['brand']),
            trim($data['productName']),
            $data['currency'],
            (float)$data['price'],
            (float)$data['salePrice'],
            self::validateEnum($data['layout'], self::ALLOWED_LAYOUTS, 'standard', 'layout'),
            trim($data['description']),
            (bool)$data['showReviewPanel'],
            $data['review'],
            $data['product_id'],
            $data['variant_id'],
            (bool)$data['opted_out_product_match']
        );
    }

    public function toArray(): array
    {
        return [
            'link' => $this->link,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'displayAs' => $this->displayAs,
            'linkText' => $this->linkText,
            'image' => $this->image,
            'name' => $this->name,
            'brand' => $this->brand,
            'productName' => $this->productName,
            'currency' => $this->currency,
            'price' => $this->price,
            'salePrice' => $this->salePrice,
            'layout' => $this->layout,
            'description' => $this->description,
            'showReviewPanel' => $this->showReviewPanel,
            'review' => $this->review,
            'has_sale_price' => $this->hasSalePrice(),
            'description_word_count' => str_word_count(strip_tags($this->description)),
            'formatted_description' => nl2br(htmlspecialchars($this->description)),
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'opted_out_product_match' => $this->optedOutProductMatch
        ];
    }

    public function hasSalePrice(): bool
    {
        return $this->salePrice > 0 && $this->salePrice < $this->price;
    }

    public function getType(): string
    {
        return 'product';
    }
}