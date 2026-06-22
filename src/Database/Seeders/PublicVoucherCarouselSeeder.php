<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Site;
use App\Models\Voucher;

class PublicVoucherCarouselSeeder extends Seeder
{
    public function run(): void
    {
        $sites = Site::where('is_active', true)->get();

        if ($sites->isEmpty()) {
            echo "No active sites found. Public vouchers were not seeded.\n";
            return;
        }

        foreach ($sites as $site) {
            $this->seedSite((int) $site->id);
        }

        echo "Public voucher carousel vouchers seeded successfully.\n";
    }

    private function seedSite(int $siteId): void
    {
        foreach ($this->vouchers() as $voucher) {
            $existing = Voucher::where('site_id', $siteId)
                ->where('code', $voucher['code'])
                ->first();

            $payload = array_merge($voucher, [
                'site_id' => $siteId,
                'status' => 'active',
                'usage_count' => 0,
                'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'expires_at' => date('Y-m-d H:i:s', strtotime($voucher['expires_in'])),
            ]);

            unset($payload['expires_in']);

            if ($existing) {
                foreach ($payload as $key => $value) {
                    $existing->{$key} = $value;
                }

                $existing->save();
                continue;
            }

            Voucher::create($payload);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function vouchers(): array
    {
        return [
            [
                'code' => 'WELCOME15',
                'name' => '15% off your first order',
                'description' => 'A simple welcome discount for new readers buying from the marketplace.',
                'type' => 'percentage',
                'value' => 15.00,
                'minimum_order_value' => 25.00,
                'maximum_discount' => 40.00,
                'usage_limit' => 500,
                'per_user_limit' => 1,
                'expires_in' => '+45 days',
                'terms_and_conditions' => 'New customers only. Cannot be combined with non-stackable offers.',
            ],
            [
                'code' => 'SAVE20',
                'name' => '20% off selected products',
                'description' => 'A stronger seasonal code for featured products and campaign landing pages.',
                'type' => 'percentage',
                'value' => 20.00,
                'minimum_order_value' => 50.00,
                'maximum_discount' => 75.00,
                'usage_limit' => 300,
                'per_user_limit' => 1,
                'expires_in' => '+30 days',
                'terms_and_conditions' => 'Applies while the voucher is active and usage limits remain available.',
            ],
            [
                'code' => 'TENNER',
                'name' => '£10 off your basket',
                'description' => 'A fixed basket discount for readers who are ready to check out today.',
                'type' => 'fixed',
                'value' => 10.00,
                'minimum_order_value' => 60.00,
                'maximum_discount' => null,
                'usage_limit' => 250,
                'per_user_limit' => 1,
                'expires_in' => '+21 days',
                'terms_and_conditions' => 'Minimum basket value applies before shipping and taxes.',
            ],
            [
                'code' => 'VIP25',
                'name' => '25% VIP reader saving',
                'description' => 'A limited high-value code for hero landing pages and premium placements.',
                'type' => 'percentage',
                'value' => 25.00,
                'minimum_order_value' => 100.00,
                'maximum_discount' => 100.00,
                'usage_limit' => 100,
                'per_user_limit' => 1,
                'expires_in' => '+14 days',
                'terms_and_conditions' => 'Limited allocation. Voucher may expire early when all redemptions are used.',
            ],
        ];
    }
}
