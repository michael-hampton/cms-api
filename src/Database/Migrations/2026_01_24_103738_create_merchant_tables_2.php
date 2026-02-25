<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMerchantTables2 extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('site_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('site_id')->references('id')->on('sites');
        });

        Schema::create('merchant_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('role')->nullable(); // e.g., 'Technical', 'Billing'
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
