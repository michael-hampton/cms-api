<?php

namespace App\Actions\Brief;

use App\Framework\Database\Database;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\ImageRepository;
use App\Repositories\Cms\UserRepository;
use App\Services\Cms\PageService;
use Exception;

class ConvertBriefToPage
{
    public function __construct(
        private readonly Database         $database,
        private readonly BriefRepository  $briefRepository,
        private readonly PageService      $pageService,
        private readonly AuthorRepository $authorRepository,
        private readonly UserRepository   $userRepository,
        private ImageRepository           $imageRepository
    )
    {

    }

    public function handle(int $briefId, array $conversionData): array
    {
        return $this->database->transaction(function () use ($briefId, $conversionData) {
            $brief = $this->briefRepository->getCompleteBriefData($briefId);
            if (!$brief) {
                throw new Exception("Brief not found");
            }

            // Prepare page data
            $pageData = [
                'forms' => [
                    'main' => [
                        'title' => $conversionData['title'] ?? $brief->title
                    ],
                    'meta' => [
                        'slug' => $this->generateSlug($conversionData['title'] ?? $brief->title),
                        'status' => 'draft'
                    ]
                ],
                'blocks' => []
            ];

            // Add images as image blocks
            $blockOrder = 0;
            foreach ($conversionData['images'] as $imageData) {
                $attachment = $this->briefRepository->getAttachment($imageData['attachment_id']);

                if (!$attachment || $attachment->type !== 'image') {
                    continue;
                }

                $image = $this->imageRepository->find($imageData['image_id']);
                if (!$image) {
                    continue;
                }

                $metadata = $attachment->metadata ?? [];

                $pageData['blocks'][] = [
                    'type' => 'image',
                    'src' => $image->file_path,
                    'url' => $image->file_path,
                    'image_id' => $image->id,
                    'alt' => $metadata['alt_text'] ?? $imageData['alt_text'] ?? '',
                    'credit' => $metadata['credit'] ?? $imageData['credit'] ?? '',
                    'caption' => $metadata['caption'] ?? $imageData['caption'] ?? '',
                    'linkUrl' => $metadata['linkUrl'] ?? '',
                    'noFollow' => (bool)($metadata['noFollow'] ?? false),
                    'sponsored' => (bool)($metadata['sponsored'] ?? false),
                    'openInNewTab' => (bool)($metadata['openInNewTab'] ?? false),
                    'layout' => $metadata['layout'] ?? 'full',
                    'alignment' => $metadata['alignment'] ?? 'center',
                    'context' => $metadata['context'] ?? 'default',
                    'order' => $blockOrder++
                ];
            }

            // Add products as product/deal blocks
            foreach ($conversionData['products'] as $productData) {
                $attachment = $this->briefRepository->getAttachment($productData['attachment_id']);

                if (!$attachment) {
                    continue;
                }

                $blockType = $productData['conversion_type'] ?? 'product';
                $metadata = $attachment->metadata ?? [];

                // Common fields for both types
                $commonFields = [
                    'product_id' => $metadata['product_id'] ?? null,
                    'variant_id' => $metadata['variant_id'] ?? null,
                    'productName' => $metadata['productName'] ?? '',
                    'brand' => $metadata['brand'] ?? '',
                    'description' => $metadata['description'] ?? '',
                    'price' => (float)($metadata['price'] ?? 0),
                    'salePrice' => (float)($metadata['salePrice'] ?? 0),
                    'currency' => $metadata['currency'] ?? '',
                    'link' => $metadata['link'] ?? $attachment->url ?? '',
                    'image' => isset($metadata['image']) ? $metadata['image'] : null,
                    'noFollow' => (bool)($metadata['noFollow'] ?? false),
                    'sponsored' => (bool)($metadata['sponsored'] ?? false),
                    'openInNewTab' => (bool)($metadata['openInNewTab'] ?? true),
                    'order' => $blockOrder++
                ];

                if ($blockType === 'deal') {
                    $pageData['blocks'][] = array_merge($commonFields, [
                        'type' => 'deal',
                        'title' => $metadata['title'] ?? $metadata['productName'] ?? '',
                        'showDealButton' => (bool)($metadata['showDealButton'] ?? true),
                        'starBlock' => (bool)($metadata['starBlock'] ?? false),
                        'savingMode' => $metadata['savingMode'] ?? 'none',
                        'voucherId' => $metadata['voucherId'] ?? '',
                        'context' => $metadata['context'] ?? 'default',
                    ]);
                } else {
                    $pageData['blocks'][] = array_merge($commonFields, [
                        'type' => 'product',
                        'name' => $conversionData['title'] ?? '',
                        'displayAs' => $metadata['displayAs'] ?? 'button',
                        'linkText' => $metadata['linkText'] ?? 'Buy Now',
                        'layout' => $metadata['layout'] ?? 'standard',
                        'showReviewPanel' => false,
                        'opted_out_product_match' => (bool)($metadata['opted_out_product_match'] ?? false),
                        'review' => $metadata['review'] ?? null,
                    ]);
                }
            }

            // Set owner and category
            if (!empty($conversionData['owner_id'])) {
                $user = $this->userRepository->find($conversionData['owner_id']);

                if ($user) {
                    $author = $this->authorRepository->findOrCreateFromUser($user, $brief->site_id);
                    $pageData['forms']['meta']['authors'] = [$author->id];
                }
            }

            if (!empty($conversionData['category_id'])) {
                $pageData['forms']['tags'] = [
                    'categories' => [$conversionData['category_id']]
                ];
            }

            // Create the page
            $page = $this->pageService->createPageWithAllData($pageData, $brief->site_id);

            // Mark brief as converted
            $this->briefRepository->markAsConverted($briefId, $page->id);

            return [
                'success' => true,
                'page_id' => $page->id,
                'brief_id' => $briefId
            ];
        });
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}