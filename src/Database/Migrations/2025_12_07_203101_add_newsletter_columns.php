<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddNewsletterColumns extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            $table->string('content_type')->nullable();
            $table->json('page_filters')->nullable();
            $table->integer('max_pages')->default(1);
            $table->string('sort_by')->nullable();
            $table->string('sort_order')->nullable();
            $table->string('template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
