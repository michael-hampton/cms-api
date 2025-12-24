<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Site;

class EnsureProductImagesSeeder extends Seeder
{

    public function run(): void
    {
        $sites = Site::with(['products.images'])->get();

        foreach ($sites as $site) {
            foreach ($site->products as $product) {

                // Case 1: No images at all
                if ($product->images->isEmpty()) {
                    $this->addFallbackImage($product);
                    continue;
                }

                // Case 2: Images exist, but all are broken
                $hasValidImage = false;

                foreach ($product->images as $image) {
                    if ($this->isImageValid($image->url)) {
                        $hasValidImage = true;
                        break;
                    }
                }

                if (!$hasValidImage) {
                    $this->addFallbackImage($product);
                }
            }
        }
    }

    protected function addFallbackImage(Product $product): void
    {
        echo 'adding image to ' . $product->name;

        ProductImage::create([
            'product_id' => $product->id,
            'url' => $this->unsplashFallback(),
            'is_primary' => 1,
        ]);

        $product->image = $this->unsplashFallback();
        $product->save();
    }

    protected function unsplashFallback(): string
    {
        return 'https://via.placeholder.com/800x800.png?text=Product';
    }

    protected function isImageValid(string $url): bool
    {
        try {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,        // HEAD request
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            curl_exec($ch);

            if (curl_errno($ch)) {
                curl_close($ch);
                return false;
            }

            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $statusCode >= 200 && $statusCode < 300;
        } catch (\Exception $e) {
            return false;
        }
    }

}