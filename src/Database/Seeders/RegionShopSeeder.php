<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Product;
use App\Models\ProductRegionSet;
use App\Models\RegionSet;

class RegionShopSeeder extends Seeder
{
    private const REGION_SETS = [
        ['name' => 'United Kingdom', 'slug' => 'uk', 'sort_order' => 1],
        ['name' => 'Europe', 'slug' => 'europe', 'sort_order' => 2],
        ['name' => 'United States', 'slug' => 'us', 'sort_order' => 3],
        ['name' => 'Australia', 'slug' => 'australia', 'sort_order' => 4],
        ['name' => 'Rest of World', 'slug' => 'rest-of-world', 'sort_order' => 5],
    ];

    private const PRODUCTS = [
        [
            'name' => 'British Wool Blanket',
            'description' => 'Hand-woven wool blanket from the Yorkshire Dales.',
            'price' => 89.99,
            'sale_price' => 0,
            'regions' => ['uk'],
        ],
        [
            'name' => 'London Fog Tea Caddy',
            'description' => 'Premium loose-leaf tea caddy, dispatched from our London warehouse.',
            'price' => 24.99,
            'sale_price' => 19.99,
            'regions' => ['uk'],
        ],
        [
            'name' => 'Alpine Hiking Poles',
            'description' => 'Lightweight carbon-fibre poles suited for Alpine terrain.',
            'price' => 119.00,
            'sale_price' => 0,
            'regions' => ['europe'],
        ],
        [
            'name' => 'Vienna Roast Coffee',
            'description' => 'Single-origin Arabica roasted in Vienna.',
            'price' => 18.50,
            'sale_price' => 15.00,
            'regions' => ['europe'],
        ],
        [
            'name' => 'Americana Denim Jacket',
            'description' => 'Classic selvedge denim jacket, made in the USA.',
            'price' => 199.00,
            'sale_price' => 159.00,
            'regions' => ['us'],
        ],
        [
            'name' => 'US Standard Power Strip',
            'description' => '6-outlet power strip with surge protection, 120V.',
            'price' => 34.99,
            'sale_price' => 0,
            'regions' => ['us'],
        ],
        [
            'name' => 'Merino Sun Shirt',
            'description' => 'UPF 50+ merino wool shirt, ideal for the Australian summer.',
            'price' => 79.00,
            'sale_price' => 65.00,
            'regions' => ['australia'],
        ],
        [
            'name' => 'Outback Canteen',
            'description' => 'Insulated 1L stainless steel canteen for long days outdoors.',
            'price' => 44.99,
            'sale_price' => 0,
            'regions' => ['australia'],
        ],
        [
            'name' => 'European Plug Adapter Set',
            'description' => 'Covers Type C, F, and G sockets across the UK and Europe.',
            'price' => 29.99,
            'sale_price' => 24.99,
            'regions' => ['uk', 'europe'],
        ],
        [
            'name' => 'Premium Leather Wallet',
            'description' => 'Full-grain leather bifold wallet, available in the UK and Europe.',
            'price' => 59.00,
            'sale_price' => 0,
            'regions' => ['uk', 'europe'],
        ],
        [
            'name' => 'Wireless Charging Pad',
            'description' => '15W fast wireless charger, compatible with US and Australian voltage.',
            'price' => 49.99,
            'sale_price' => 39.99,
            'regions' => ['us', 'australia'],
        ],
        [
            'name' => 'Universal Travel Adapter',
            'description' => 'Works in over 150 countries. Suitable for worldwide shipping.',
            'price' => 39.99,
            'sale_price' => 0,
            'regions' => ['rest-of-world'],
        ],
        [
            'name' => 'Stainless Steel Water Bottle',
            'description' => 'Double-walled 500ml bottle. Available everywhere.',
            'price' => 22.00,
            'sale_price' => 18.00,
            'regions' => [],
        ],
        [
            'name' => 'Bamboo Phone Stand',
            'description' => 'Adjustable bamboo desk stand. Ships globally.',
            'price' => 14.99,
            'sale_price' => 0,
            'regions' => [],
        ],
        [
            'name' => 'Microfibre Cloth 5-Pack',
            'description' => 'Multi-surface cleaning cloths. Available in all regions.',
            'price' => 9.99,
            'sale_price' => 0,
            'regions' => [],
        ],
    ];

    public function run(): void
    {
        $siteId = 7;

        $regionSets = $this->seedRegionSets($siteId);

        $this->seedProducts($siteId, $regionSets);
    }

    /**
     * @return array<string, RegionSet>
     */
    private function seedRegionSets(int $siteId): array
    {
        $regionSets = [];

        foreach (self::REGION_SETS as $data) {
            $regionSet = RegionSet::where('slug', $data['slug'])
                ->where('site_id', $siteId)
                ->first();

            if ($regionSet) {
                $regionSets[$data['slug']] = $regionSet;
                continue;
            }

            $regionSets[$data['slug']] = RegionSet::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sort_order' => $data['sort_order'],
                'is_active' => true,
                'site_id' => $siteId,
            ]);
        }

        return $regionSets;
    }

    /**
     * @param array<string, RegionSet> $regionSets
     */
    private function seedProducts(int $siteId, array $regionSets): void
    {
        foreach (self::PRODUCTS as $data) {
            $slug = $this->slugify($data['name']);

            $product = Product::where('slug', $slug)
                ->where('site_id', $siteId)
                ->first();

            if (!$product) {
                $product = Product::create([
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'sale_price' => $data['sale_price'] > 0 ? $data['sale_price'] : 0,
                    'is_active' => true,
                    'stock_quantity' => 100,
                    'site_id' => $siteId,
                    'slug' => $slug,
                ]);
            }

            $regionSetIds = $this->resolveRegionSetIds($data['regions'], $regionSets);

            ProductRegionSet::where('product_id', $product->id)->delete();

            foreach ($regionSetIds as $regionSetId) {

                ProductRegionSet::create([
                    'product_id' => $product->id,
                    'region_set_id' => $regionSetId,
                ]);
            }
        }
    }

    /**
     * @param array<int, string> $slugs
     * @param array<string, RegionSet> $regionSets
     *
     * @return array<int, int>
     */
    private function resolveRegionSetIds(array $slugs, array $regionSets): array
    {
        $ids = [];

        foreach ($slugs as $slug) {
            if (!isset($regionSets[$slug])) {
                continue;
            }

            $ids[] = (int) $regionSets[$slug]->id;
        }

        return $ids;
    }

    private function slugify(string $name): string
    {
        return strtolower(trim(
            preg_replace('/[^a-zA-Z0-9]+/', '-', $name),
            '-'
        ));
    }
}