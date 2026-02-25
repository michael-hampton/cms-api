<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDetailsToMerchantsTable extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('is_active');
            $table->foreignId('contact_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->string('description')->nullable()->after('contact_id');
            $table->string('primary_url')->nullable()->after('description');

            $table->foreign('contact_id')->references('id')->on('merchant_contacts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
