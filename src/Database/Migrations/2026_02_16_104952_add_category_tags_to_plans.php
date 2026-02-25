<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCategoryTagsToPlans extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Add category field for filtering (if it doesn't exist)
            $table->json('category')->nullable()->after('description');


            // Add tags field for filtering (if it doesn't exist)
            $table->json('tags')->nullable()->after('category');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
