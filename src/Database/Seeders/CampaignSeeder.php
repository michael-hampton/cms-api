<?php
// seeders/CampaignSeeder.php

namespace App\Database\Seeders;

use App\Framework\Database\Database;
use App\Models\Newsletter;
use App\Models\Site;

class CampaignSeeder
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function run(): void
    {
        $sites = Site::all();

        foreach ($sites as $site) {
            $this->seedCampaignsForSite($site);
        }
    }

    private function seedCampaignsForSite($site): void
    {
        // Get first newsletter for this site
        $newsletter = Newsletter::where('site_id', $site->id)
            ->where('active', true)
            ->first();

        if (!$newsletter) {
            return;
        }

        $campaigns = [
            [
                'site_id' => $site->id,
                'name' => 'Welcome Campaign',
                'slug' => 'welcome',
                'description' => 'Welcome new subscribers to our newsletter',
                'newsletter_id' => $newsletter->id,
                'is_active' => true,
                'gates_premium_content' => 0,
                'start_date' => null,
                'end_date' => null,
                'tracking_params' => json_encode([
                    'utm_source' => 'website',
                    'utm_medium' => 'modal',
                    'utm_campaign' => 'welcome'
                ]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'site_id' => $site->id,
                'name' => 'Premium Content Access',
                'slug' => 'premium-access',
                'description' => 'Subscribe to unlock premium content',
                'newsletter_id' => $newsletter->id,
                'is_active' => true,
                'gates_premium_content' => 1,
                'start_date' => null,
                'end_date' => null,
                'tracking_params' => json_encode([
                    'utm_source' => 'website',
                    'utm_medium' => 'paywall',
                    'utm_campaign' => 'premium'
                ]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'site_id' => $site->id,
                'name' => 'Social Media Campaign',
                'slug' => 'social-promo',
                'description' => 'Newsletter signup from social media promotion',
                'newsletter_id' => $newsletter->id,
                'is_active' => true,
                'gates_premium_content' => 0,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'tracking_params' => json_encode([
                    'utm_source' => 'facebook',
                    'utm_medium' => 'social',
                    'utm_campaign' => 'spring-promo'
                ]),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        foreach ($campaigns as $campaign) {
            $this->database->query(
                "INSERT INTO campaigns (site_id, name, slug, description, newsletter_id, is_active, 
            gates_premium_content, start_date, end_date, tracking_params, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $campaign['site_id'],
                    $campaign['name'],
                    $campaign['slug'],
                    $campaign['description'],
                    $campaign['newsletter_id'],
                    $campaign['is_active'],
                    $campaign['gates_premium_content'],
                    $campaign['start_date'],
                    $campaign['end_date'],
                    $campaign['tracking_params'],
                    $campaign['created_at'],
                    $campaign['updated_at']
                ]
            );
        }
    }
}