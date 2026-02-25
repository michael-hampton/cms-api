<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateProductRewardDefinitionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_reward_definitions', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('reward_definition_id');

            $table->primary(['product_id', 'reward_definition_id']);

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->onDelete('cascade');

            $table->foreign('reward_definition_id')
                ->references('id')->on('reward_definitions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
