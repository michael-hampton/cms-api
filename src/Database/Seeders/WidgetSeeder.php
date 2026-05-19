<?php

namespace App\Database\Seeders;

use App\Framework\Database\Database;
use App\Framework\Database\Seeder\Seeder;
use App\Models\User;

class WidgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the widget keys to seed
        $widgetKeys = ['activity', 'drafts', 'earnings', 'onboarding', 'quick_links'];

        // Fetch all users
        $users = User::all();

        $data = [];

        foreach ($users as $user) {
            foreach ($widgetKeys as $index => $key) {
                $data[] = [
                    'user_id'    => $user['id'],
                    'widget_key' => $key,
                    'enabled'    => 1,
                    'position'   => $index, // Automatically increments position (0 to 4) per user
                    'settings'   => null,   // Nullable as per your schema
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in chunks of 500 rows to keep memory usage low
            if (count($data) >= 500) {
                Database::table('oc_contributor_dashboard_widgets')->insert($data);
                $data = []; // Reset the array
            }
        }

        // Insert any remaining records
        if (!empty($data)) {
            Database::table('oc_contributor_dashboard_widgets')->insert($data);
        }
    }
}