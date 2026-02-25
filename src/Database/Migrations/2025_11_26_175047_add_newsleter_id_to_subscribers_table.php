<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddNewsleterIdToSubscribersTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->foreignId('newsletter_id')->nullable();
            $table->foreign('newsletter_id')->references('id')->on('newsletters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
