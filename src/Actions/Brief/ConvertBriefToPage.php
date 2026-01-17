<?php

namespace App\Actions\Brief;

use App\Framework\Database\Database;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\BriefRepository;
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

                $pageData['blocks'][] = [
                    'type' => 'image',
                    'src' => $image->file_path,
                    'url' => $image->file_path,
                    'alt' => $imageData['alt_text'] ?? '',
                    'credit' => $imageData['credit'] ?? '',
                    'caption' => $imageData['caption'] ?? '',
                    'order' => $blockOrder++,
                    'image_id' => $image->id
                ];
            }

            // Add products as product/deal blocks
            // Replace the products loop section with:
            foreach ($conversionData['products'] as $productData) {
                $attachment = $this->briefRepository->getAttachment($productData['attachment_id']);
                if (!$attachment || $attachment->type !== 'product') {
                    continue;
                }

                $blockType = $productData['conversion_type'] ?? 'product';
                $metadata = $attachment->metadata ?? [];

                if ($blockType === 'deal') {
                    $pageData['blocks'][] = [
                        'type' => 'deal',
                        'title' => $metadata['product_name'] ?? $conversionData['title'] ?? '',
                        'productName' => $metadata['product_name'] ?? $conversionData['title'] ?? '',
                        'brand' => $metadata['brand'] ?? '',
                        'description' => $metadata['description'] ?? $conversionData['description'] ?? '',
                        'price' => (float)str_replace(['$', '£', ','], '', $metadata['product_price'] ?? '0'),
                        'salePrice' => (float)str_replace(['$', '£', ','], '', $metadata['sale_price'] ?? '0'),
                        'currency' => $metadata['currency'] ?? '£',
                        'link' => $attachment->url ?? $productData['url'] ?? '',
                        'image' => $attachment->image_id ? ['src' => $attachment->image->file_path ?? ''] : null,
                        'noFollow' => (bool)($metadata['no_follow'] ?? false),
                        'sponsored' => (bool)($metadata['sponsored'] ?? false),
                        'openInNewTab' => (bool)($metadata['open_in_new_tab'] ?? true),
                        'showDealButton' => true,
                        'starBlock' => false,
                        'savingMode' => 'percent',
                        'voucherId' => $metadata['voucher_id'] ?? '',
                        'context' => 'default',
                        'product_id' => $productData['product_id'] ?? null,
                        'order' => $blockOrder++
                    ];
                } else {
                    $pageData['blocks'][] = [
                        'type' => 'product',
                        'name' => $metadata['product_name'] ?? '',
                        'productName' => $metadata['product_name'] ?? '',
                        'brand' => $metadata['brand'] ?? '',
                        'description' => $metadata['description'] ?? '',
                        'price' => (float)str_replace(['$', '£', ','], '', $metadata['product_price'] ?? '0'),
                        'salePrice' => (float)str_replace(['$', '£', ','], '', $metadata['sale_price'] ?? '0'),
                        'currency' => $metadata['currency'] ?? '$',
                        'link' => $attachment->url ?? $productData['url'] ?? '',
                        'image' => $attachment->image_id ? ['src' => $attachment->image->file_path ?? ''] : null,
                        'noFollow' => (bool)($metadata['no_follow'] ?? false),
                        'sponsored' => (bool)($metadata['sponsored'] ?? false),
                        'openInNewTab' => (bool)($metadata['open_in_new_tab'] ?? true),
                        'displayAs' => 'button',
                        'linkText' => 'Buy Now',
                        'layout' => 'standard',
                        'showReviewPanel' => false,
                        'product_id' => $productData['product_id'] ?? null,
                        'variant_id' => $productData['variant_id'] ?? null,
                        'opted_out_product_match' => false,
                        'order' => $blockOrder++
                    ];
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