<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCacheStoreTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cache_store')) {
            return;
        }

        Schema::create('cache_store', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_store');
    }
}
