<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToClicks extends Migration
{
    public function up(): void
    {
        Schema::table('offer_clicks', function (Blueprint $table) {
            $table->integer('deal_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('surface_type')->nullable();
            $table->integer('surface_id')->nullable();
            $table->dateTime('clicked_at')->nullable();
        });

        Schema::table('reward_clicks', function (Blueprint $table) {
            $table->integer('deal_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('surface_type')->nullable();
            $table->integer('surface_id')->nullable();
            $table->dateTime('clicked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
