<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCloneRecords extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });

        Schema::table('region_sets', function (Blueprint $table) {
            $table->json('clone_history')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
