<?php

namespace App\Parsers\Dtos;

final class DealBlockDto extends BaseBlockDto
{
    private const ALLOWED_CONTEXTS = ['default', 'sidebar'];
    private const ALLOWED_SAVING_MODES = ['percent', 'amount', 'none'];

    private const KNOWN_KEYS = [
        'link', 'noFollow', 'sponsored', 'openInNewTab', 'title', 'brand',
        'productName', 'image', 'currency', 'price', 'salePrice',
        'description', 'showDealButton', 'starBlock',
    ];

    public function __construct(
        public string $link,
        public bool   $noFollow,
        public bool   $sponsored,
        public bool   $openInNewTab,
        public string $title,
        public string $brand,
        public string $productName,
        public ?array $image,
        public string $currency,
        public float  $price,
        public float  $salePrice,
        public string $savingMode,
        public string $description,
        public bool   $showDealButton,
        public bool   $starBlock,
        public string $voucherId,
        public string $context,
        public ?int   $product_id,
        public ?int   $variant_id,
        public bool   $opted_out_product_match
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
            'title' => '',
            'brand' => '',
            'productName' => '',
            'image' => null,
            'currency' => '£',
            'price' => 0.0,
            'salePrice' => 0.0,
            'savingMode' => 'percent',
            'description' => '',
            'showDealButton' => true,
            'starBlock' => false,
            'voucherId' => '',
            'context' => 'default',
            'product_id' => null,
            'variant_id' => null,
            'opted_out_product_match' => false
        ]);

        $price = (float)$data['price'];
        $salePrice = (float)$data['salePrice'];
        $savings = $price > $salePrice ? $price - $salePrice : 0;

        return new self(
            $data['link'],
            (bool)$data['noFollow'],
            (bool)$data['sponsored'],
            (bool)$data['openInNewTab'],
            trim($data['title']),
            trim($data['brand']),
            trim($data['productName']),
            $data['image'],
            $data['currency'],
            $price,
            $salePrice,
            self::validateEnum($data['savingMode'], self::ALLOWED_SAVING_MODES, 'percent', 'savingMode'),
            trim($data['description']),
            (bool)$data['showDealButton'],
            (bool)$data['starBlock'],
            $data['voucherId'],
            self::validateEnum($data['context'], self::ALLOWED_CONTEXTS, 'default', 'context'),
            $data['product_id'],
            $data['variant_id'],
            (bool)$data['opted_out_product_match']
        );
    }

    public function toArray(): array
    {
        $savings = $this->price > $this->salePrice ? $this->price - $this->salePrice : 0;
        $savingsPercent = $this->price > 0 ? round(($savings / $this->price) * 100) : 0;

        $attributes = [];

        if ($this->openInNewTab) {
            $attributes['target'] = '_blank';
            $attributes['rel'] = 'noopener noreferrer';
        }

        $relValues = [];
        if ($this->noFollow) $relValues[] = 'nofollow';
        if ($this->sponsored) $relValues[] = 'sponsored';

        if (!empty($relValues)) {
            if (isset($attributes['rel'])) {
                $attributes['rel'] .= ' ' . implode(' ', $relValues);
            } else {
                $attributes['rel'] = implode(' ', $relValues);
            }
        }

        return [
            'title' => $this->title,
            'productName' => $this->productName,
            'brand' => $this->brand,
            'description' => $this->description,
            'price' => $this->price,
            'salePrice' => $this->salePrice,
            'currency' => $this->currency,
            'link' => $this->link,
            'image' => $this->image,
            'noFollow' => $this->noFollow,
            'sponsored' => $this->sponsored,
            'openInNewTab' => $this->openInNewTab,
            'showDealButton' => $this->showDealButton,
            'starBlock' => $this->starBlock,
            'savings' => $savings,
            'savings_percent' => $savingsPercent,
            'savingMode' => $this->savingMode,
            'has_savings' => $savings > 0,
            'voucherId' => $this->voucherId,
            'has_voucher' => !empty($this->voucherId),
            'formatted_description' => nl2br(htmlspecialchars($this->description)),
            'link_attributes' => $attributes,
            'context' => $this->context,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'opted_out_product_match' => $this->opted_out_product_match,
        ];
    }

    public function getType(): string
    {
        return 'deal';
    }
}