<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageWidgetsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id');
            $table->string('widget_key', 100);
            $table->string('region', 50);
            $table->integer('priority')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['page_id', 'widget_key']);
            $table->index(['page_id', 'region', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_widgets');
    }
}
