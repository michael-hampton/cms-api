<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterLayoutsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('layout_definition_json');
            $table->boolean('is_system_layout')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('is_system_layout');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
