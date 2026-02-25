<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateEditorialOverrides extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_id')->nullable();   // null = global
            $table->foreignId('member_id')->nullable();

            $table->enum('override_access_level', ['free', 'member', 'premium']);

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('page_id')->references('id')->on('pages');
            $table->foreign('member_id')->references('id')->on('members');

            // Optional indexes
            $table->index(['page_id']);
            $table->index(['member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
