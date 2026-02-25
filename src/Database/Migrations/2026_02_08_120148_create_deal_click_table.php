<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateDealClickTable extends Migration
{
    public function up(): void
    {
        Schema::create('deal_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('site_id');
            $table->string('action'); // 'render', 'click'
            $table->string('channel'); // 'newsletter', 'web'
            $table->string('surface_type'); // 'newsletter_issue', 'page'
            $table->unsignedBigInteger('surface_id');
            $table->integer('deal_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['product_id', 'action', 'created_at']);
            $table->index(['member_id', 'action', 'created_at']);
            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
