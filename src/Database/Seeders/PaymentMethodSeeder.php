<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\PaymentMethod;
use App\Models\Site;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $sites = Site::all();

        $methods = [
            [
                'name' => 'Credit/Debit Card (Stripe)',
                'code' => 'stripe',
                'provider' => 'stripe',
                'is_active' => true,
                'requires_processing' => true,
                'instructions' => 'Pay securely with your credit or debit card via Stripe.',
                'sort_order' => 1
            ],
            [
                'name' => 'PayPal',
                'code' => 'paypal',
                'provider' => 'paypal',
                'is_active' => true,
                'requires_processing' => true,
                'instructions' => 'Pay securely with your PayPal account.',
                'sort_order' => 2
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'bank_transfer',
                'provider' => null,
                'is_active' => true,
                'requires_processing' => false,
                'instructions' => 'Please transfer the payment to our bank account. Order will be processed once payment is confirmed.',
                'sort_order' => 3
            ],
            [
                'name' => 'Cash on Delivery',
                'code' => 'cash_on_delivery',
                'provider' => null,
                'is_active' => true,
                'requires_processing' => false,
                'instructions' => 'Pay with cash when your order is delivered.',
                'sort_order' => 4
            ],
        ];


        foreach ($sites as $site) {
            $siteId = $site->id;

            foreach ($methods as $method) {
                $method['site_id'] = $siteId;
                PaymentMethod::create($method);
            }
        }
    }
}