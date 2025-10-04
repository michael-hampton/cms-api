<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBlocksTable extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('type', 50);
            $table->json('data');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['page_id', 'order']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::drop('blocks');
    }
}