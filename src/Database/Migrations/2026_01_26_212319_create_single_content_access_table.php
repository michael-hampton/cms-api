<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSingleContentAccessTable extends Migration
{
    public function up(): void
    {
        Schema::create('single_content_access', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->string('content_type', 50);
            $table->unsignedBigInteger('content_id');

            $table->string('access_token', 64)->unique();

            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');

            $table->foreignId('payment_id')->nullable();

            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();

            $table->timestamps(); // created_at & updated_at

            // Indexes
            $table->index('member_id', 'idx_member');
            $table->index(['content_type', 'content_id'], 'idx_content');
            $table->index('access_token', 'idx_token');
            $table->index('expires_at', 'idx_expires');

            // Foreign keys
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->onDelete('cascade');

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            $table->foreign('payment_id')
                ->references('id')
                ->on('payments')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
