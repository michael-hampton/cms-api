<?php

namespace App\Database\Seeders;


use App\Framework\Database\Database;
use App\Framework\Database\Seeder\Seeder;

/**
 * CountriesSeeder
 *
 * Seeds every country Stripe Tax supports as of 2025.
 * Codes are ISO 3166-1 alpha-2 — identical to what Stripe accepts on:
 *   - Customer.address.country
 *   - PaymentIntent / Tax Calculation address.country
 *   - Checkout Session shipping_address_collection.allowed_countries
 *
 * sort_order convention
 * ─────────────────────
 *  10  — tier-1 markets (GB, US, AU, CA, IE, NZ)
 *  20  — Western Europe
 *  30  — Northern Europe
 *  40  — Southern / Eastern Europe
 *  50  — Asia-Pacific
 *  60  — Americas (ex US/CA)
 *  70  — Middle East / Africa
 * 100  — everything else (default)
 *
 * has_states is TRUE only for US and CA because those are the only countries
 * where the application currently collects and uses state for tax calculation.
 * Add more as needed.
 *
 * Run:  php artisan db:seed --class=CountriesSeeder
 */
class CountriesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $countries = [
            // ── Tier-1 markets (sort 10) ──────────────────────────────────
            ['code' => 'GB', 'name' => 'United Kingdom',       'has_states' => false, 'sort_order' => 10],
            ['code' => 'US', 'name' => 'United States',        'has_states' => true,  'sort_order' => 10],
            ['code' => 'AU', 'name' => 'Australia',            'has_states' => false, 'sort_order' => 10],
            ['code' => 'CA', 'name' => 'Canada',               'has_states' => true,  'sort_order' => 10],
            ['code' => 'IE', 'name' => 'Ireland',              'has_states' => false, 'sort_order' => 10],
            ['code' => 'NZ', 'name' => 'New Zealand',          'has_states' => false, 'sort_order' => 10],

            // ── Western Europe (sort 20) ───────────────────────────────────
            ['code' => 'AT', 'name' => 'Austria',              'has_states' => false, 'sort_order' => 20],
            ['code' => 'BE', 'name' => 'Belgium',              'has_states' => false, 'sort_order' => 20],
            ['code' => 'CH', 'name' => 'Switzerland',          'has_states' => false, 'sort_order' => 20],
            ['code' => 'DE', 'name' => 'Germany',              'has_states' => false, 'sort_order' => 20],
            ['code' => 'ES', 'name' => 'Spain',                'has_states' => false, 'sort_order' => 20],
            ['code' => 'FR', 'name' => 'France',               'has_states' => false, 'sort_order' => 20],
            ['code' => 'IT', 'name' => 'Italy',                'has_states' => false, 'sort_order' => 20],
            ['code' => 'LU', 'name' => 'Luxembourg',           'has_states' => false, 'sort_order' => 20],
            ['code' => 'MC', 'name' => 'Monaco',               'has_states' => false, 'sort_order' => 20],
            ['code' => 'NL', 'name' => 'Netherlands',          'has_states' => false, 'sort_order' => 20],
            ['code' => 'PT', 'name' => 'Portugal',             'has_states' => false, 'sort_order' => 20],

            // ── Northern Europe (sort 30) ──────────────────────────────────
            ['code' => 'DK', 'name' => 'Denmark',              'has_states' => false, 'sort_order' => 30],
            ['code' => 'EE', 'name' => 'Estonia',              'has_states' => false, 'sort_order' => 30],
            ['code' => 'FI', 'name' => 'Finland',              'has_states' => false, 'sort_order' => 30],
            ['code' => 'IS', 'name' => 'Iceland',              'has_states' => false, 'sort_order' => 30],
            ['code' => 'LT', 'name' => 'Lithuania',            'has_states' => false, 'sort_order' => 30],
            ['code' => 'LV', 'name' => 'Latvia',               'has_states' => false, 'sort_order' => 30],
            ['code' => 'NO', 'name' => 'Norway',               'has_states' => false, 'sort_order' => 30],
            ['code' => 'SE', 'name' => 'Sweden',               'has_states' => false, 'sort_order' => 30],

            // ── Southern / Eastern Europe (sort 40) ───────────────────────
            ['code' => 'BG', 'name' => 'Bulgaria',             'has_states' => false, 'sort_order' => 40],
            ['code' => 'CY', 'name' => 'Cyprus',               'has_states' => false, 'sort_order' => 40],
            ['code' => 'CZ', 'name' => 'Czech Republic',       'has_states' => false, 'sort_order' => 40],
            ['code' => 'GR', 'name' => 'Greece',               'has_states' => false, 'sort_order' => 40],
            ['code' => 'HR', 'name' => 'Croatia',              'has_states' => false, 'sort_order' => 40],
            ['code' => 'HU', 'name' => 'Hungary',              'has_states' => false, 'sort_order' => 40],
            ['code' => 'LI', 'name' => 'Liechtenstein',        'has_states' => false, 'sort_order' => 40],
            ['code' => 'MT', 'name' => 'Malta',                'has_states' => false, 'sort_order' => 40],
            ['code' => 'PL', 'name' => 'Poland',               'has_states' => false, 'sort_order' => 40],
            ['code' => 'RO', 'name' => 'Romania',              'has_states' => false, 'sort_order' => 40],
            ['code' => 'SI', 'name' => 'Slovenia',             'has_states' => false, 'sort_order' => 40],
            ['code' => 'SK', 'name' => 'Slovakia',             'has_states' => false, 'sort_order' => 40],

            // ── Asia-Pacific (sort 50) ─────────────────────────────────────
            ['code' => 'HK', 'name' => 'Hong Kong',            'has_states' => false, 'sort_order' => 50],
            ['code' => 'JP', 'name' => 'Japan',                'has_states' => false, 'sort_order' => 50],
            ['code' => 'KR', 'name' => 'South Korea',          'has_states' => false, 'sort_order' => 50],
            ['code' => 'MY', 'name' => 'Malaysia',             'has_states' => false, 'sort_order' => 50],
            ['code' => 'PH', 'name' => 'Philippines',          'has_states' => false, 'sort_order' => 50],
            ['code' => 'SG', 'name' => 'Singapore',            'has_states' => false, 'sort_order' => 50],
            ['code' => 'TH', 'name' => 'Thailand',             'has_states' => false, 'sort_order' => 50],
            ['code' => 'TW', 'name' => 'Taiwan',               'has_states' => false, 'sort_order' => 50],

            // ── Americas (sort 60) ─────────────────────────────────────────
            ['code' => 'AR', 'name' => 'Argentina',            'has_states' => false, 'sort_order' => 60],
            ['code' => 'BR', 'name' => 'Brazil',               'has_states' => false, 'sort_order' => 60],
            ['code' => 'CL', 'name' => 'Chile',                'has_states' => false, 'sort_order' => 60],
            ['code' => 'CO', 'name' => 'Colombia',             'has_states' => false, 'sort_order' => 60],
            ['code' => 'MX', 'name' => 'Mexico',               'has_states' => false, 'sort_order' => 60],
            ['code' => 'PE', 'name' => 'Peru',                 'has_states' => false, 'sort_order' => 60],

            // ── Middle East / Africa (sort 70) ────────────────────────────
            ['code' => 'AE', 'name' => 'United Arab Emirates', 'has_states' => false, 'sort_order' => 70],
            ['code' => 'IL', 'name' => 'Israel',               'has_states' => false, 'sort_order' => 70],
            ['code' => 'SA', 'name' => 'Saudi Arabia',         'has_states' => false, 'sort_order' => 70],
            ['code' => 'ZA', 'name' => 'South Africa',         'has_states' => false, 'sort_order' => 70],
        ];

        foreach ($countries as &$row) {
            $row['is_active']   = true;
            $row['created_at']  = $now;
            $row['updated_at']  = $now;
        }
        unset($row);

        // Upsert so the seeder is safe to re-run (e.g. after adding a country).
        // `name` and `sort_order` may be corrected on re-run; `is_active` is
        // intentionally excluded so manual deactivations are not overwritten.
        Database::table('countries')->upsert(
            $countries,
            ['code'],                          // unique key
            ['name', 'has_states', 'sort_order', 'updated_at']
        );
    }
}